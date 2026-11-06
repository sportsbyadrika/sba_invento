<?php
use App\Core\Auth;
use App\Core\Database;

return [
    'title' => 'Warehouses',
    'singular' => 'Warehouse',
    'icon' => 'warehouse',
    'table' => 'warehouses',
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code'],
        'name' => ['label' => 'Name', 'sql' => 't.name'],
        'address' => ['label' => 'Address', 'sql' => 't.address'],
        'is_default' => ['label' => 'Default', 'sql' => 't.is_default', 'format' => 'bool'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'search' => ['t.code', 't.name', 't.address'],
    'filters' => ['is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']]],
    'stats' => [['label' => 'Warehouses', 'sql' => 'COUNT(*)', 'icon' => 'warehouse']],
    'fields' => [
        'code' => ['label' => 'Code', 'required' => true, 'max' => 20, 'unique' => true, 'uppercase' => true],
        'name' => ['label' => 'Name', 'required' => true, 'max' => 100],
        'address' => ['label' => 'Address', 'type' => 'textarea', 'max' => 255, 'col' => 2],
        'is_default' => ['label' => 'Default warehouse', 'type' => 'checkbox'],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'after_save' => static function (int $id, array $d): void {
            if (!empty($d['is_default'])) {
                Database::query('UPDATE warehouses SET is_default = 0 WHERE company_id = ? AND id <> ?', [Auth::instance()->companyId(), $id]);
            }
        },
    ],
];
