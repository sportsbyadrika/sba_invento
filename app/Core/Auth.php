<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\LoginLogger;

/**
 * Authentication, active company context and role permissions.
 */
final class Auth
{
    private static ?Auth $instance = null;
    private ?array $user = null;
    private ?array $company = null;
    private ?array $companies = null;
    private ?string $role = null;
    private ?array $permissions = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Restore the authenticated user from the session for this request. */
    public function boot(): void
    {
        $uid = Session::get('uid');
        if (!$uid) {
            return;
        }
        $idle = (int) config('security.session_idle_minutes', 30) * 60;
        $last = (int) Session::get('last_activity', 0);
        $fp = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (($idle > 0 && $last && time() - $last > $idle) || Session::get('fp') !== $fp) {
            $this->logout('Session expired');
            Session::start();
            Session::flash('error', 'Your session expired due to inactivity. Please sign in again.');
            return;
        }
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$user || $user['status'] !== 'active') {
            $this->logout('Account not active');
            Session::start();
            return;
        }
        Session::set('last_activity', time());
        $this->user = $user;
        $this->loadCompany((int) Session::get('cid', 0));
    }

    private function loadCompany(int $cid): void
    {
        $this->company = null;
        $this->role = null;
        $companies = $this->companies();
        if (!$companies) {
            return;
        }
        $pick = null;
        foreach ($companies as $c) {
            if ((int) $c['id'] === $cid) {
                $pick = $c;
                break;
            }
        }
        if (!$pick) {
            foreach ($companies as $c) {
                if ((int) $c['id'] === (int) ($this->user['default_company_id'] ?? 0)) {
                    $pick = $c;
                    break;
                }
            }
        }
        $pick ??= $companies[0];
        $company = Database::one('SELECT * FROM companies WHERE id = ?', [$pick['id']]);
        if (!$company) {
            return;
        }
        $company['_decimals'] = (int) (Database::value(
            'SELECT decimal_places FROM currencies WHERE company_id = ? AND code = ?',
            [$company['id'], $company['base_currency']]
        ) ?? 2);
        $this->company = $company;
        $this->role = $pick['role'];
        Session::set('cid', (int) $company['id']);
    }

    /** Run as a user in a company without a web session (installer, scheduled jobs, shop postings). */
    public function actAs(array $user, int $companyId): void
    {
        $this->user = $user;
        $this->companies = null;
        $this->permissions = null;
        $this->loadCompany($companyId);
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user ? (int) $this->user['id'] : null;
    }

    public function company(): ?array
    {
        return $this->company;
    }

    public function companyId(): ?int
    {
        return $this->company ? (int) $this->company['id'] : null;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function isSuper(): bool
    {
        return (bool) ($this->user['is_super_admin'] ?? false);
    }

    public function isAdmin(): bool
    {
        return $this->isSuper() || $this->role === 'admin';
    }

    /** Companies this user can switch to (super admin: all active companies). */
    public function companies(): array
    {
        if ($this->companies !== null) {
            return $this->companies;
        }
        if (!$this->user) {
            return [];
        }
        if ($this->isSuper()) {
            $this->companies = Database::all(
                "SELECT c.id, c.code, c.name, c.base_currency, 'admin' AS role FROM companies c WHERE c.status = 'active' ORDER BY c.name"
            );
        } else {
            $this->companies = Database::all(
                "SELECT c.id, c.code, c.name, c.base_currency, uc.role
                   FROM user_companies uc JOIN companies c ON c.id = uc.company_id
                  WHERE uc.user_id = ? AND uc.is_active = 1 AND c.status = 'active'
               ORDER BY c.name",
                [$this->user['id']]
            );
        }
        return $this->companies;
    }

    public function switchCompany(int $companyId): bool
    {
        foreach ($this->companies() as $c) {
            if ((int) $c['id'] === $companyId) {
                Session::set('cid', $companyId);
                $this->loadCompany($companyId);
                return true;
            }
        }
        return false;
    }

    /** @param string $action view|create|edit|delete */
    public function can(string $module, string $action = 'view'): bool
    {
        if (!$this->user) {
            return false;
        }
        if ($this->isSuper()) {
            return true;
        }
        if (!$this->role) {
            return false;
        }
        if ($this->permissions === null) {
            $roles = (require BASE_PATH . '/config/permissions.php')['roles'];
            $this->permissions = $roles[$this->role]['access'] ?? [];
        }
        $level = $this->permissions[$module] ?? $this->permissions['*'] ?? null;
        return match ($level) {
            'full' => true,
            'edit' => $action !== 'delete',
            'view' => $action === 'view',
            default => false,
        };
    }

    /**
     * Verify credentials, enforce lock-out and write the login log.
     * @return array{ok:bool, error?:string}
     */
    public function attempt(string $username, string $password, array $client): array
    {
        $generic = 'Invalid username or password.';
        $ip = client_ip();
        $maxAttempts = (int) config('security.max_login_attempts', 5);

        // IP based throttling (protects against password spraying across many usernames)
        $ipFails = (int) Database::value(
            "SELECT COUNT(*) FROM login_logs WHERE ip_address = ? AND event IN ('failed','locked') AND created_at > ?",
            [$ip, gmdate('Y-m-d H:i:s', time() - 60 * (int) config('security.ip_window_minutes', 15))]
        );
        if ($ipFails >= (int) config('security.ip_max_failures', 20)) {
            LoginLogger::log('failed', null, $username, false, 'IP throttled', $client);
            return ['ok' => false, 'error' => 'Too many failed attempts from your network. Please try again later.'];
        }

        $user = Database::one('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$user) {
            password_verify($password, self::hash('timing-equaliser')); // similar response time for unknown users
            LoginLogger::log('failed', null, $username, false, 'Unknown username', $client);
            return ['ok' => false, 'error' => $generic];
        }
        if ($user['status'] === 'disabled') {
            LoginLogger::log('failed', (int) $user['id'], $username, false, 'Account disabled', $client);
            return ['ok' => false, 'error' => 'Your account is disabled. Please contact your company administrator.'];
        }
        if ($user['status'] === 'locked') {
            LoginLogger::log('failed', (int) $user['id'], $username, false, 'Account locked', $client);
            return ['ok' => false, 'error' => 'Your account is locked after too many failed attempts. Reset your password using "Forgot password" or contact your administrator.'];
        }
        if (!password_verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_attempts'] + 1;
            if ($fails >= $maxAttempts) {
                Database::update('users', ['failed_attempts' => $fails, 'status' => 'locked', 'locked_at' => now_utc()], 'id = ?', [$user['id']]);
                LoginLogger::log('locked', (int) $user['id'], $username, false, "Locked after $fails failed attempts", $client);
                return ['ok' => false, 'error' => 'Too many failed attempts. Your account has been locked. Use "Forgot password" to unlock it.'];
            }
            Database::update('users', ['failed_attempts' => $fails], 'id = ?', [$user['id']]);
            LoginLogger::log('failed', (int) $user['id'], $username, false, 'Wrong password', $client);
            $left = $maxAttempts - $fails;
            return ['ok' => false, 'error' => $generic . ($left <= 2 ? " $left attempt(s) left before the account is locked." : '')];
        }

        $hasCompany = $user['is_super_admin'] || Database::value(
            "SELECT COUNT(*) FROM user_companies uc JOIN companies c ON c.id = uc.company_id
              WHERE uc.user_id = ? AND uc.is_active = 1 AND c.status = 'active'",
            [$user['id']]
        );
        if (!$hasCompany) {
            LoginLogger::log('failed', (int) $user['id'], $username, false, 'No active company', $client);
            return ['ok' => false, 'error' => 'Your user is not linked to any active company. Please contact your administrator.'];
        }

        if (password_needs_rehash($user['password_hash'], self::algo())) {
            Database::update('users', ['password_hash' => self::hash($password)], 'id = ?', [$user['id']]);
        }
        $this->login($user, $client);
        return ['ok' => true];
    }

    private function login(array $user, array $client): void
    {
        $update = [
            'failed_attempts' => 0,
            'last_login_at'   => now_utc(),
            'last_login_ip'   => client_ip(),
        ];
        if (empty($user['timezone']) && !empty($client['tz']) && in_array($client['tz'], \DateTimeZone::listIdentifiers(), true)) {
            $update['timezone'] = $client['tz'];
        }
        Database::update('users', $update, 'id = ?', [$user['id']]);

        Session::regenerate();
        $_SESSION = [];
        Session::set('uid', (int) $user['id']);
        Session::set('cid', (int) ($user['default_company_id'] ?? 0));
        Session::set('last_activity', time());
        Session::set('fp', hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        LoginLogger::log('login', (int) $user['id'], $user['username'], true, null, $client);
    }

    public function logout(string $reason = 'User logout'): void
    {
        if ($uid = Session::get('uid')) {
            $username = (string) Database::value('SELECT username FROM users WHERE id = ?', [$uid]);
            LoginLogger::log('logout', (int) $uid, $username, true, $reason, []);
        }
        Session::destroy();
        $this->user = $this->company = $this->companies = null;
        $this->role = null;
    }

    public static function algo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo());
    }

    /** @return string|null error message or null when the password is acceptable */
    public static function passwordPolicyError(string $password): ?string
    {
        $min = (int) config('security.password_min_length', 8);
        if (mb_strlen($password) < $min) {
            return "Password must be at least $min characters.";
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password must contain upper-case, lower-case letters and a number.';
        }
        return null;
    }
}
