<?php
/**
 * Role based access per module.  'full' = view/create/edit/delete,
 * 'edit' = view/create/edit, 'view' = read only.
 * The company 'admin' role always has full access (including settings).
 */
return [
    'modules' => [
        'dashboard'  => 'Dashboard',
        'accountant' => 'Accountant',
        'inventory'  => 'Inventory',
        'sales'      => 'Sales',
        'purchases'  => 'Purchases',
        'banking'    => 'Banking',
        'reports'    => 'Reports',
        'settings'   => 'Settings',
    ],
    'roles' => [
        'admin'      => ['label' => 'Company Admin', 'access' => ['*' => 'full']],
        'accountant' => ['label' => 'Accountant', 'access' => [
            'dashboard' => 'view', 'accountant' => 'full', 'banking' => 'full', 'reports' => 'view',
            'sales' => 'edit', 'purchases' => 'edit', 'inventory' => 'view',
        ]],
        'sales'      => ['label' => 'Sales User', 'access' => [
            'dashboard' => 'view', 'sales' => 'edit', 'inventory' => 'view', 'reports' => 'view',
        ]],
        'purchase'   => ['label' => 'Purchase / Store User', 'access' => [
            'dashboard' => 'view', 'purchases' => 'edit', 'inventory' => 'edit', 'reports' => 'view',
        ]],
        'viewer'     => ['label' => 'Viewer (read only)', 'access' => [
            'dashboard' => 'view', 'accountant' => 'view', 'inventory' => 'view', 'sales' => 'view',
            'purchases' => 'view', 'banking' => 'view', 'reports' => 'view',
        ]],
    ],
];
