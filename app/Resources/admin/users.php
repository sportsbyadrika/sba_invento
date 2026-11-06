<?php
use App\Core\Database;
use App\Services\PasswordReset;

$status = ['active' => ['Active', 'green'], 'locked' => ['Locked', 'rose'], 'disabled' => ['Disabled', 'slate']];

return [
    'title' => 'All Users',
    'singular' => 'User',
    'icon' => 'users',
    'super_only' => true,
    'description' => 'Every user on the platform with the companies they can access.',
    'table' => 'users',
    'tenant' => false,
    'from' => 'users t',
    'can_create' => false,
    'can_delete' => false,
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'username' => ['label' => 'Username', 'sql' => 't.username', 'format' => 'code'],
        'name' => ['label' => 'Name', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium">' . e($r['name']) . '</span>' . ($r['is_super_admin'] ? ' <span class="badge badge-violet ml-1">Super admin</span>' : '') . '<span class="block text-xs text-slate-500">' . e($r['email']) . '</span>'],
        'companies' => ['label' => 'Companies', 'sql' => "(SELECT GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ') FROM user_companies uc JOIN companies c ON c.id = uc.company_id WHERE uc.user_id = t.id)"],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => $status],
        'last_login_at' => ['label' => 'Last Login', 'sql' => 't.last_login_at', 'format' => 'datetime'],
        'last_login_ip' => ['label' => 'Last IP', 'sql' => 't.last_login_ip', 'format' => 'code'],
    ],
    'select_extra' => ['email' => 't.email', 'is_super_admin' => 't.is_super_admin'],
    'search' => ['t.username', 't.name', 't.email'],
    'filters' => [
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], $status)],
        'is_super_admin' => ['label' => 'Super admin', 'type' => 'select', 'sql' => 't.is_super_admin', 'options' => ['1' => 'Yes', '0' => 'No']],
        'company' => ['label' => 'Company', 'type' => 'select', 'in_template' => 'EXISTS (SELECT 1 FROM user_companies uc WHERE uc.user_id = t.id AND uc.company_id IN ({in}))', 'sql' => '', 'options' => static fn () => array_column(Database::all('SELECT id, name FROM companies ORDER BY name'), 'name', 'id')],
    ],
    'stats' => [
        ['label' => 'Users', 'sql' => 'COUNT(*)', 'icon' => 'users'],
        ['label' => 'Locked', 'sql' => "COALESCE(SUM(t.status='locked'),0)", 'icon' => 'lock', 'tone' => 'rose'],
        ['label' => 'Active last 30 days', 'sql' => 'COALESCE(SUM(t.last_login_at > UTC_TIMESTAMP() - INTERVAL 30 DAY),0)', 'icon' => 'activity', 'tone' => 'emerald'],
    ],
    'fields' => [
        'name' => ['label' => 'Full Name', 'required' => true, 'max' => 120, 'col' => 2],
        'email' => ['label' => 'E-mail', 'type' => 'email', 'required' => true, 'max' => 150, 'col' => 2],
        'status' => ['label' => 'Status', 'type' => 'select', 'options' => array_map(static fn ($s) => $s[0], $status)],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $e): array {
            if ($e && $e['status'] !== 'active' && $d['status'] === 'active') {
                $d['failed_attempts'] = 0;
                $d['locked_at'] = null;
            }
            return $d;
        },
    ],
    'row_actions' => [
        'unlock' => ['label' => 'Unlock', 'icon' => 'lock-open', 'tone' => 'emerald', 'visible' => static fn ($r) => $r['status'] === 'locked',
            'handler' => static function ($row) {
                Database::update('users', ['status' => 'active', 'failed_attempts' => 0, 'locked_at' => null], 'id = ?', [$row['id']]);
                return 'User unlocked.';
            }],
        'reset-link' => ['label' => 'Send reset link', 'icon' => 'mail', 'confirm' => 'E-mail a password reset link to this user?', 'visible' => static fn ($r) => $r['status'] !== 'disabled',
            'handler' => static function ($row) {
                PasswordReset::send($row);
                return 'Reset link sent.';
            }],
    ],
];
