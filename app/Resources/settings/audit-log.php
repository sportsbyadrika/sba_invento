<?php
return [
    'title' => 'Audit Trail',
    'singular' => 'Audit entry',
    'icon' => 'history',
    'admin_only' => true,
    'description' => 'Who changed what and when. Entries cannot be edited or deleted.',
    'table' => 'audit_logs',
    'from' => 'audit_logs t LEFT JOIN users u ON u.id = t.user_id',
    'readonly' => true,
    'can_create' => false,
    'can_delete' => false,
    'columns' => [
        'created_at' => ['label' => 'Time (local)', 'sql' => 't.created_at', 'format' => 'datetime'],
        'user' => ['label' => 'User', 'sql' => 'u.name'],
        'entity' => ['label' => 'Record', 'sql' => 't.entity'],
        'entity_id' => ['label' => 'ID', 'sql' => 't.entity_id', 'format' => 'code'],
        'action' => ['label' => 'Action', 'sql' => 't.action', 'format' => 'badge', 'map' => ['create' => ['Create', 'green'], 'update' => ['Update', 'blue'], 'delete' => ['Delete', 'rose'], 'export' => ['Export', 'violet']]],
        'changes' => ['label' => 'Changes', 'sql' => 't.changes', 'sortable' => false, 'render' => static function ($r) {
            $c = json_decode((string) $r['changes'], true);
            if (!$c) {
                return '';
            }
            $out = [];
            foreach (array_slice($c, 0, 4, true) as $k => $v) {
                $out[] = '<span class="text-slate-500">' . e($k) . ':</span> ' . e(mb_strimwidth((string) ($v['from'] ?? '∅'), 0, 30, '…')) . ' → <span class="text-slate-900">' . e(mb_strimwidth((string) ($v['to'] ?? '∅'), 0, 30, '…')) . '</span>';
            }
            return '<span class="text-xs">' . implode('<br>', $out) . (count($c) > 4 ? '<br>…' : '') . '</span>';
        }],
        'ip_address' => ['label' => 'IP', 'sql' => 't.ip_address', 'format' => 'code'],
    ],
    'default_sort' => ['created_at', 'desc'],
    'search' => ['t.entity', 't.entity_id', 'u.name', 't.action'],
    'filters' => [
        'action' => ['label' => 'Action', 'type' => 'select', 'sql' => 't.action', 'options' => ['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete', 'export' => 'Export', 'post' => 'Post', 'void' => 'Void']],
        'entity' => ['label' => 'Record type', 'type' => 'text', 'sql' => 't.entity'],
        'date' => ['label' => 'Date (UTC)', 'type' => 'daterange', 'sql' => 't.created_at'],
    ],
    'stats' => [['label' => 'Entries', 'sql' => 'COUNT(*)', 'icon' => 'history'], ['label' => 'Deletes', 'sql' => "COALESCE(SUM(t.action='delete'),0)", 'icon' => 'trash-2', 'tone' => 'rose']],
    'fields' => [],
];
