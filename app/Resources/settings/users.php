<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Services\PasswordReset;

$roles = array_map(static fn ($r) => $r['label'], (require BASE_PATH . '/config/permissions.php')['roles']);
$status = ['active' => ['Active', 'green'], 'locked' => ['Locked', 'rose'], 'disabled' => ['Disabled', 'slate']];
$auth = Auth::instance();
/** companies the current admin may assign users to */
$myCompanies = static function () use ($auth): array {
    if ($auth->isSuper()) {
        return array_column(Database::all("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name"), 'name', 'id');
    }
    return array_column(Database::all(
        "SELECT c.id, c.name FROM user_companies uc JOIN companies c ON c.id = uc.company_id
          WHERE uc.user_id = ? AND uc.role = 'admin' AND uc.is_active = 1 AND c.status = 'active' ORDER BY c.name",
        [$auth->id()]
    ), 'name', 'id');
};

return [
    'title' => 'Users',
    'singular' => 'User',
    'icon' => 'users',
    'admin_only' => true,
    'description' => 'Users of this company. A user can be linked to several companies and switch between them from the profile menu.',
    'table' => 'users',
    'tenant' => 'uc.company_id',
    'tenant_column' => null,
    'from' => 'users t JOIN user_companies uc ON uc.user_id = t.id',
    'form_select' => 't.*, uc.role, uc.is_active AS link_active',
    'creator' => true,
    'can_delete' => false,
    'columns' => [
        'username' => ['label' => 'Username', 'lowercase' => true, 'sql' => 't.username', 'format' => 'code'],
        'name' => ['label' => 'Name', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['name']) . '</span><span class="block text-xs text-slate-500">' . e($r['email']) . '</span>'],
        'role' => ['label' => 'Role', 'sql' => 'uc.role', 'format' => 'badge', 'map' => array_map(static fn ($l) => [$l, 'blue'], $roles)],
        'companies' => ['label' => 'Companies', 'sql' => '(SELECT COUNT(*) FROM user_companies x WHERE x.user_id = t.id AND x.is_active = 1)', 'align' => 'right'],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => $status],
        'failed_attempts' => ['label' => 'Failed', 'sql' => 't.failed_attempts', 'align' => 'right'],
        'last_login_at' => ['label' => 'Last Login', 'sql' => 't.last_login_at', 'format' => 'datetime'],
    ],
    'select_extra' => ['email' => 't.email', 'user_status' => 't.status'],
    'default_sort' => ['name', 'asc'],
    'search' => ['t.username', 't.name', 't.email'],
    'filters' => [
        'role' => ['label' => 'Role', 'type' => 'select', 'sql' => 'uc.role', 'options' => $roles],
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], $status)],
        'last_login' => ['label' => 'Last Login', 'type' => 'daterange', 'sql' => 't.last_login_at'],
    ],
    'stats' => [
        ['label' => 'Users', 'sql' => 'COUNT(*)', 'icon' => 'users'],
        ['label' => 'Admins', 'sql' => "COALESCE(SUM(uc.role = 'admin'),0)", 'icon' => 'shield-check', 'tone' => 'indigo'],
        ['label' => 'Locked', 'sql' => "COALESCE(SUM(t.status = 'locked'),0)", 'icon' => 'lock', 'tone' => 'rose'],
        ['label' => 'Never logged in', 'sql' => 'COALESCE(SUM(t.last_login_at IS NULL),0)', 'icon' => 'user-x', 'tone' => 'amber'],
    ],
    'fields' => [
        'username' => ['label' => 'Username', 'required' => true, 'max' => 50, 'unique' => true, 'unique_global' => true, 'create_only' => true,
            'pattern' => '/^[a-z0-9][a-z0-9._\-]{2,49}$/', 'pattern_message' => '3-50 characters: lower-case letters, digits, dot, dash or underscore.', 'help' => 'Used to sign in. Cannot be changed later.'],
        'name' => ['label' => 'Full Name', 'required' => true, 'max' => 120],
        'email' => ['label' => 'E-mail', 'type' => 'email', 'required' => true, 'max' => 150, 'help' => 'Password reset links are sent here.'],
        'phone' => ['label' => 'Phone', 'max' => 40],
        'role' => ['label' => 'Role in this company', 'type' => 'select', 'required' => true, 'options' => $roles, 'virtual' => true, 'default' => 'viewer'],
        'status' => ['label' => 'Status', 'type' => 'select', 'options' => array_map(static fn ($s) => $s[0], $status), 'edit_only' => true],
        'password' => ['label' => 'Password', 'type' => 'password', 'virtual' => true, 'required' => static fn ($in, $e) => $e === null, 'help' => 'Min. 8 characters with upper, lower case and a number. Leave empty to keep the current password.'],
        'must_change_password' => ['label' => 'Ask the user to change the password at next login', 'type' => 'checkbox', 'default' => 1, 'col' => 2],
        'section_companies' => ['label' => 'Company access', 'type' => 'section'],
        'companies' => ['label' => 'Linked companies', 'type' => 'multiselect', 'virtual' => true, 'options' => $myCompanies, 'col' => 2, 'help' => 'The current company is always included. The same role is applied to the selected companies.'],
    ],
    'hooks' => [
        'load' => static function (array $row): array {
            $row['companies'] = array_column(Database::all('SELECT company_id FROM user_companies WHERE user_id = ? AND is_active = 1', [$row['id']]), 'company_id');
            return $row;
        },
        'before_save' => static function (array $d, ?array $e) use ($auth): array {
            if (!$e) {
                $d['username'] = strtolower($d['username']);
                $company = $auth->company();
                $count = (int) Database::value('SELECT COUNT(*) FROM user_companies WHERE company_id = ? AND is_active = 1', [$company['id']]);
                if ($count >= (int) $company['max_users'] && !$auth->isSuper()) {
                    throw new ValidationException(['username' => "Your plan allows {$company['max_users']} users. Contact support to upgrade."]);
                }
            }
            if (isset($d['password'])) {
                if ($err = Auth::passwordPolicyError((string) $d['password'])) {
                    throw new ValidationException(['password' => $err]);
                }
                $d['password_hash'] = Auth::hash((string) $d['password']);
                $d['password_changed_at'] = now_utc();
            }
            if ($e && (int) $e['id'] === $auth->id()) {
                if (($d['status'] ?? 'active') !== 'active') {
                    throw new ValidationException(['status' => 'You cannot disable or lock your own account.']);
                }
                if ($d['role'] !== 'admin') {
                    throw new ValidationException(['role' => 'You cannot remove your own admin role.']);
                }
            }
            if ($e && $e['status'] !== 'active' && ($d['status'] ?? '') === 'active') {
                $d['failed_attempts'] = 0;
                $d['locked_at'] = null;
            }
            if (($d['status'] ?? '') === 'locked' && $e && $e['status'] !== 'locked') {
                $d['locked_at'] = now_utc();
            }
            return $d;
        },
        'after_save' => static function (int $id, array $d, ?array $e) use ($auth, $myCompanies): void {
            $allowed = array_map('intval', array_keys($myCompanies()));
            $selected = array_map('intval', $d['companies'] ?? []);
            $selected[] = (int) $auth->companyId();
            $selected = array_values(array_unique(array_intersect($selected, $allowed ?: [(int) $auth->companyId()]) ?: [(int) $auth->companyId()]));
            foreach ($selected as $cid) {
                Database::query(
                    'INSERT INTO user_companies (user_id, company_id, role, is_active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = 1',
                    [$id, $cid, $d['role']]
                );
            }
            // unlink from companies this admin manages but that were not selected
            $remove = array_diff($allowed, $selected);
            if ($remove && $id !== $auth->id()) {
                Database::query('DELETE FROM user_companies WHERE user_id = ? AND company_id IN (' . implode(',', array_map('intval', $remove)) . ')', [$id]);
            }
            if (!$e) {
                Database::update('users', ['default_company_id' => $auth->companyId()], 'id = ?', [$id]);
            }
        },
    ],
    'row_actions' => [
        'unlock' => [
            'label' => 'Unlock', 'icon' => 'lock-open', 'tone' => 'emerald', 'confirm' => 'Unlock this user and reset failed attempts?',
            'visible' => static fn ($r) => ($r['user_status'] ?? $r['status']) === 'locked',
            'handler' => static function ($row) {
                Database::update('users', ['status' => 'active', 'failed_attempts' => 0, 'locked_at' => null], 'id = ?', [$row['id']]);
                return 'User ' . $row['username'] . ' unlocked.';
            },
        ],
        'reset-link' => [
            'label' => 'Send reset link', 'icon' => 'mail', 'confirm' => 'E-mail a password reset link to this user?',
            'visible' => static fn ($r) => ($r['user_status'] ?? $r['status']) !== 'disabled',
            'handler' => static function ($row) {
                PasswordReset::send($row);
                return 'Password reset link sent to ' . $row['email'] . '.';
            },
        ],
        'remove' => [
            'label' => 'Remove from company', 'icon' => 'user-minus', 'tone' => 'rose', 'confirm' => 'Remove this user\'s access to the current company? The user account itself is kept.',
            'visible' => static fn ($r) => (int) ($r['__id'] ?? $r['id']) !== Auth::instance()->id(),
            'handler' => static function ($row) {
                Database::query('DELETE FROM user_companies WHERE user_id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]);
                return 'User removed from this company.';
            },
        ],
    ],
];
