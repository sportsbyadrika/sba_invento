<?php
use App\Services\Shop;

return [
    'title' => 'Returns',
    'singular' => 'Return',
    'icon' => 'undo-2',
    'description' => 'Customer return requests: approve, receive the goods (credit note + stock back) and refund.',
    'table' => 'shop_returns',
    'from' => 'shop_returns t JOIN shop_orders o ON o.id = t.order_id JOIN shop_customers c ON c.id = t.customer_id',
    'module' => 'shop',
    'readonly' => true, 'can_create' => false, 'can_delete' => false,
    'row_url' => '/shop/returns/{id}',
    'columns' => [
        'created_at' => ['label' => 'Requested', 'sql' => 't.created_at', 'format' => 'datetime'],
        'number' => ['label' => 'Return #', 'sql' => 't.number', 'format' => 'code'],
        'order' => ['label' => 'Order', 'sql' => 'o.number', 'format' => 'code'],
        'customer' => ['label' => 'Customer', 'sql' => 'c.name'],
        'reason' => ['label' => 'Reason', 'sql' => 't.reason', 'render' => static fn ($r) => e(Shop::RETURN_REASONS[$r['reason']] ?? $r['reason'])],
        'refund_amount' => ['label' => 'Refund', 'sql' => 't.refund_amount', 'format' => 'money', 'align' => 'right'],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => Shop::RETURN_STATUS],
    ],
    'default_sort' => ['created_at', 'desc'],
    'search' => ['t.number', 'o.number', 'c.name'],
    'filters' => [
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], Shop::RETURN_STATUS)],
        'reason' => ['label' => 'Reason', 'type' => 'select', 'sql' => 't.reason', 'options' => Shop::RETURN_REASONS],
        'date' => ['label' => 'Requested', 'type' => 'daterange', 'sql' => 't.created_at'],
    ],
    'stats' => [
        ['label' => 'Returns', 'sql' => 'COUNT(*)', 'icon' => 'undo-2'],
        ['label' => 'Awaiting decision', 'sql' => "COALESCE(SUM(t.status = 'requested'),0)", 'icon' => 'bell', 'tone' => 'amber'],
        ['label' => 'To refund', 'sql' => "COALESCE(SUM(t.status = 'received'),0)", 'icon' => 'banknote', 'tone' => 'rose'],
        ['label' => 'Refunded value', 'sql' => "COALESCE(SUM(CASE WHEN t.status = 'refunded' THEN t.refund_amount END),0)", 'format' => 'money', 'icon' => 'wallet', 'tone' => 'slate'],
    ],
    'fields' => [],
];
