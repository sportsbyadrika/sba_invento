<?php
use App\Core\Database;

return [
    'title' => 'Units of Measure',
    'singular' => 'Unit',
    'icon' => 'ruler',
    'table' => 'units',
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code'],
        'name' => ['label' => 'Name', 'sql' => 't.name'],
        'uqc' => ['label' => 'GST UQC', 'sql' => 't.uqc', 'format' => 'code'],
        'decimals' => ['label' => 'Decimals', 'sql' => 't.decimals', 'align' => 'right'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'default_sort' => ['code', 'asc'],
    'search' => ['t.code', 't.name'],
    'filters' => ['is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']]],
    'stats' => [['label' => 'Units', 'sql' => 'COUNT(*)', 'icon' => 'ruler']],
    'fields' => [
        'code' => ['label' => 'Code', 'required' => true, 'max' => 10, 'unique' => true, 'uppercase' => true],
        'name' => ['label' => 'Name', 'required' => true, 'max' => 50],
        'uqc' => ['label' => 'GST Unit Quantity Code', 'max' => 10, 'uppercase' => true, 'help' => 'India only, e.g. NOS, KGS, LTR'],
        'decimals' => ['label' => 'Quantity Decimals', 'type' => 'number', 'min' => 0, 'default' => 0],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => ['before_delete' => static fn ($r) => Database::value('SELECT COUNT(*) FROM items WHERE unit_id = ?', [$r['id']]) ? 'Items use this unit.' : null],
];
