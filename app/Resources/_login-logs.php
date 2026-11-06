<?php
/** Shared login-log list. $scope: 'company' | 'all' | 'me' */
use App\Core\Auth;

return static function (string $scope): array {
    $events = [
        'login' => ['Login', 'green'], 'logout' => ['Logout', 'slate'], 'failed' => ['Failed', 'rose'],
        'locked' => ['Locked', 'rose'], 'reset_request' => ['Reset requested', 'amber'], 'password_reset' => ['Password reset', 'blue'],
    ];
    $where = match ($scope) {
        'me' => static fn () => ['t.user_id = ?', [Auth::instance()->id()]],
        'company' => static fn () => ['t.user_id IN (SELECT user_id FROM user_companies WHERE company_id = ?)', [Auth::instance()->companyId()]],
        default => null,
    };
    $cfg = [
        'title' => $scope === 'me' ? 'My Login History' : 'Login Log',
        'singular' => 'Login entry',
        'icon' => 'shield-check',
        'description' => 'IP address, location, machine and browser of every sign-in attempt. Times are stored in UTC and shown in your local time zone.',
        'table' => 'login_logs',
        'tenant' => false,
        'from' => 'login_logs t LEFT JOIN users u ON u.id = t.user_id',
        'readonly' => true,
        'can_create' => false,
        'can_delete' => false,
        'columns' => [
            'created_at' => ['label' => 'Time (local)', 'sql' => 't.created_at', 'format' => 'datetime', 'render' => static fn ($r) => '<span class="whitespace-nowrap">' . e(local_dt($r['created_at'], 'd-M-Y H:i:s')) . '</span><span class="block text-[11px] text-slate-400">' . e($r['created_at']) . ' UTC</span>'],
            'username' => ['label' => 'User', 'sql' => 'COALESCE(u.username, t.username_attempted)', 'render' => static fn ($r) => '<span class="font-medium">' . e($r['username']) . '</span>' . ($r['user_name'] ? '<span class="block text-xs text-slate-500">' . e($r['user_name']) . '</span>' : '')],
            'event' => ['label' => 'Event', 'sql' => 't.event', 'format' => 'badge', 'map' => $events],
            'reason' => ['label' => 'Detail', 'sql' => 't.reason'],
            'ip_address' => ['label' => 'IP Address', 'sql' => 't.ip_address', 'format' => 'code'],
            'location' => ['label' => 'Location', 'sql' => "CONCAT_WS(', ', t.city, t.region, t.country)", 'render' => static fn ($r) => e($r['location']) . ($r['isp'] ? '<span class="block text-[11px] text-slate-400">' . e($r['isp']) . '</span>' : '')],
            'browser' => ['label' => 'Browser', 'sql' => "CONCAT_WS(' ', t.browser, t.browser_version)"],
            'machine' => ['label' => 'Machine', 'sql' => "CONCAT_WS(' · ', t.os, t.device_type, t.platform, t.screen)"],
            'client_timezone' => ['label' => 'Device TZ', 'sql' => 't.client_timezone'],
        ],
        'select_extra' => ['isp' => 't.isp', 'user_name' => 'u.name'],
        'default_sort' => ['created_at', 'desc'],
        'search' => ['t.username_attempted', 't.ip_address', 't.city', 't.country', 'u.name'],
        'filters' => [
            'event' => ['label' => 'Event', 'type' => 'select', 'sql' => 't.event', 'options' => array_map(static fn ($e) => $e[0], $events)],
            'success' => ['label' => 'Result', 'type' => 'select', 'sql' => 't.success', 'options' => ['1' => 'Successful', '0' => 'Failed']],
            'date' => ['label' => 'Date (UTC)', 'type' => 'daterange', 'sql' => 't.created_at'],
            'ip' => ['label' => 'IP Address', 'type' => 'text', 'sql' => 't.ip_address'],
            'country' => ['label' => 'Country', 'type' => 'text', 'sql' => 't.country'],
            'device_type' => ['label' => 'Device', 'type' => 'select', 'sql' => 't.device_type', 'options' => ['Desktop' => 'Desktop', 'Mobile' => 'Mobile', 'Tablet' => 'Tablet', 'Bot' => 'Bot']],
        ],
        'stats' => [
            ['label' => 'Events', 'sql' => 'COUNT(*)', 'icon' => 'activity'],
            ['label' => 'Successful logins', 'sql' => "COALESCE(SUM(t.event='login'),0)", 'icon' => 'log-in', 'tone' => 'emerald'],
            ['label' => 'Failed / Locked', 'sql' => "COALESCE(SUM(t.event IN ('failed','locked')),0)", 'icon' => 'shield-alert', 'tone' => 'rose'],
            ['label' => 'Distinct IPs', 'sql' => 'COUNT(DISTINCT t.ip_address)', 'icon' => 'globe', 'tone' => 'indigo'],
        ],
        'fields' => [],
    ];
    if ($where) {
        $cfg['where'] = $where;
    }
    if ($scope === 'me') {
        unset($cfg['columns']['username']);
    }
    return $cfg;
};
