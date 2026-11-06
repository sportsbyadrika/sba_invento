<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Services\LoginLogger;
use App\Services\PasswordReset;

final class AuthController
{
    public function showLogin(): string
    {
        return View::render('auth/login', ['title' => 'Sign in'], 'auth');
    }

    private function client(): array
    {
        return [
            'tz' => (string) ($_POST['client_tz'] ?? ''),
            'screen' => (string) ($_POST['client_screen'] ?? ''),
            'platform' => (string) ($_POST['client_platform'] ?? ''),
            'lang' => (string) ($_POST['client_lang'] ?? ''),
        ];
    }

    public function login(): never
    {
        $username = mb_strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '' || mb_strlen($username) > 50 || strlen($password) > 200) {
            Session::flash('error', 'Enter your username and password.');
            Session::set('_old', ['username' => $username]);
            redirect('/login');
        }
        $result = Auth::instance()->attempt($username, $password, $this->client());
        if (!$result['ok']) {
            usleep(random_int(200000, 500000)); // slow down brute force
            Session::flash('error', $result['error']);
            Session::set('_old', ['username' => $username]);
            redirect('/login');
        }
        Session::forget('_old');
        $intended = (string) Session::get('intended', '/dashboard');
        Session::forget('intended');
        // only allow local redirects
        if (!str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            $intended = '/dashboard';
        }
        $base = base_path_url();
        if ($base !== '' && str_starts_with($intended, $base)) {
            $intended = substr($intended, strlen($base)) ?: '/dashboard';
        }
        redirect($intended);
    }

    public function logout(): never
    {
        Auth::instance()->logout();
        Session::start();
        Session::flash('success', 'You have been signed out.');
        redirect('/login');
    }

    public function showForgot(): string
    {
        return View::render('auth/forgot', ['title' => 'Forgot password'], 'auth');
    }

    public function forgot(): never
    {
        $login = trim((string) ($_POST['login'] ?? ''));
        $generic = 'If the details match an account, a password reset link has been sent to the registered e-mail address.';
        if ($login === '' || mb_strlen($login) > 150) {
            Session::flash('error', 'Enter your username or e-mail address.');
            redirect('/forgot-password');
        }
        // throttle: max 5 reset requests per IP per hour
        $recent = (int) Database::value(
            "SELECT COUNT(*) FROM login_logs WHERE ip_address = ? AND event = 'reset_request' AND created_at > ?",
            [client_ip(), gmdate('Y-m-d H:i:s', time() - 3600)]
        );
        if ($recent >= 5) {
            Session::flash('success', $generic);
            redirect('/login');
        }
        $user = Database::one(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status <> 'disabled' ORDER BY username = ? DESC LIMIT 1",
            [mb_strtolower($login), $login, mb_strtolower($login)]
        );
        LoginLogger::log('reset_request', $user ? (int) $user['id'] : null, $login, (bool) $user, $user ? null : 'No matching user', []);
        if ($user) {
            PasswordReset::send($user);
        }
        Session::flash('success', $generic);
        redirect('/login');
    }

    private function tokenRow(string $token): array
    {
        if (strlen($token) !== 64) {
            throw new HttpException(404);
        }
        $row = Database::one(
            'SELECT pr.*, u.username, u.name FROM password_resets pr JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > ?',
            [hash('sha256', $token), now_utc()]
        );
        if (!$row) {
            Session::flash('error', 'This password reset link is invalid or has expired. Please request a new one.');
            redirect('/forgot-password');
        }
        return $row;
    }

    public function showReset(string $token): string
    {
        $row = $this->tokenRow($token);
        return View::render('auth/reset', ['title' => 'Reset password', 'token' => $token, 'username' => $row['username']], 'auth');
    }

    public function reset(string $token): never
    {
        $row = $this->tokenRow($token);
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $error = Auth::passwordPolicyError($password);
        if (!$error && $password !== $confirm) {
            $error = 'The passwords do not match.';
        }
        if (!$error && str_contains(mb_strtolower($password), mb_strtolower($row['username']))) {
            $error = 'The password must not contain your username.';
        }
        if ($error) {
            Session::flash('error', $error);
            redirect('/reset-password/' . $token);
        }
        Database::transaction(static function () use ($row, $password) {
            Database::update('users', [
                'password_hash' => Auth::hash($password),
                'status' => 'active',
                'failed_attempts' => 0,
                'locked_at' => null,
                'must_change_password' => 0,
                'password_changed_at' => now_utc(),
            ], "id = ? AND status <> 'disabled'", [$row['user_id']]);
            Database::update('password_resets', ['used_at' => now_utc()], 'id = ?', [$row['id']]);
        });
        LoginLogger::log('password_reset', (int) $row['user_id'], $row['username'], true, 'Password reset via e-mail link', []);
        Session::flash('success', 'Your password has been changed and your account is unlocked. Please sign in.');
        redirect('/login');
    }
}
