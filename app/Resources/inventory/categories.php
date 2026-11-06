<?php
use App\Core\Auth;
use App\Core\Database;

return [
    'title' => 'Item Categories',
    'singular' => 'Category',
    'icon' => 'folder-tree',
    'table' => 'item_categories',
    'from' => 'item_categories t LEFT JOIN item_categories p ON p.id = t.parent_id',
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'name' => ['label' => 'Category', 'sql' => 't.name'],
        'parent' => ['label' => 'Parent', 'sql' => 'p.name'],
        'items' => ['label' => 'Items', 'sql' => '(SELECT COUNT(*) FROM items i WHERE i.category_id = t.id)', 'align' => 'right'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'search' => ['t.name'],
    'filters' => ['is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']]],
    'stats' => [
        ['label' => 'Categories', 'sql' => 'COUNT(*)', 'icon' => 'folder-tree'],
        ['label' => 'Unused', 'sql' => 'COALESCE(SUM(NOT EXISTS (SELECT 1 FROM items i WHERE i.category_id = t.id)),0)', 'icon' => 'circle-slash', 'tone' => 'amber'],
    ],
    'fields' => [
        'name' => ['label' => 'Category Name', 'required' => true, 'max' => 100, 'unique' => true, 'col' => 2],
        'parent_id' => ['label' => 'Parent Category', 'type' => 'lookup', 'col' => 2, 'options' => static fn () => array_column(Database::all('SELECT id, name label FROM item_categories WHERE company_id = ? ORDER BY name', [Auth::instance()->companyId()]), 'label', 'id')],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $e): array {
            $d['parent_id'] = ($d['parent_id'] ?? '') ?: null;
            if ($e && (int) $d['parent_id'] === (int) $e['id']) {
                throw new App\Core\ValidationException(['parent_id' => 'A category cannot be its own parent.']);
            }
            return $d;
        },
        'before_delete' => static fn ($r) => Database::value('SELECT COUNT(*) FROM items WHERE category_id = ?', [$r['id']]) ? 'Items use this category. Re-assign them first.' : null,
    ],
];
