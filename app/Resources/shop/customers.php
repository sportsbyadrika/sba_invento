<?php
use App\Core\Auth;
use App\Core\Database;

return [
    'title' => 'Shop Customers',
    'singular' => 'Customer',
    'icon' => 'users',
    'description' => 'People registered in the online shop. An accounting customer is created for each on their first invoice.',
    'table' => 'shop_customers',
    'from' => 'shop_customers t',
    'module' => 'shop',
    'readonly' => true, 'can_create' => false, 'can_delete' => false,
    'columns' => [
        'name' => ['label' => 'Name', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['name']) . '</span><span class="block text-xs text-slate-500">' . e($r['email']) . '</span>'],
        'phone' => ['label' => 'Mobile', 'sql' => "CONCAT(t.phone_code, ' ', t.phone)"],
        'orders' => ['label' => 'Orders', 'sql' => "(SELECT COUNT(*) FROM shop_orders o WHERE o.customer_id = t.id AND o.status <> 'cancelled')", 'align' => 'right'],
        'spent' => ['label' => 'Total spent', 'sql' => "(SELECT COALESCE(SUM(o.total),0) FROM shop_orders o WHERE o.customer_id = t.id AND o.status <> 'cancelled')", 'format' => 'money', 'align' => 'right'],
        'created_at' => ['label' => 'Registered', 'sql' => 't.created_at', 'format' => 'datetime'],
        'last_login_at' => ['label' => 'Last sign-in', 'sql' => 't.last_login_at', 'format' => 'datetime'],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => ['active' => ['Active', 'green'], 'disabled' => ['Disabled', 'slate']]],
    ],
    'select_extra' => ['email' => 't.email'],
    'default_sort' => ['created_at', 'desc'],
    'search' => ['t.name', 't.email', 't.phone'],
    'filters' => [
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => ['active' => 'Active', 'disabled' => 'Disabled']],
        'created' => ['label' => 'Registered', 'type' => 'daterange', 'sql' => 't.created_at'],
    ],
    'stats' => [
        ['label' => 'Customers', 'sql' => 'COUNT(*)', 'icon' => 'users'],
        ['label' => 'New this month', 'sql' => "COALESCE(SUM(t.created_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')),0)", 'icon' => 'user-plus', 'tone' => 'emerald'],
        ['label' => 'With orders', 'sql' => 'COALESCE(SUM(EXISTS (SELECT 1 FROM shop_orders o WHERE o.customer_id = t.id)),0)', 'icon' => 'shopping-cart', 'tone' => 'cyan'],
    ],
    'fields' => [],
    'row_actions' => [
        'disable' => ['label' => 'Disable account', 'icon' => 'ban', 'tone' => 'rose', 'confirm' => 'Disable this customer account? They will not be able to sign in.', 'visible' => static fn ($r) => $r['status'] === 'active',
            'handler' => static function ($row) { Database::update('shop_customers', ['status' => 'disabled'], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]); return 'Account disabled.'; }],
        'enable' => ['label' => 'Enable account', 'icon' => 'check', 'visible' => static fn ($r) => $r['status'] === 'disabled',
            'handler' => static function ($row) { Database::update('shop_customers', ['status' => 'active', 'failed_attempts' => 0, 'locked_until' => null], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]); return 'Account enabled.'; }],
    ],
];
