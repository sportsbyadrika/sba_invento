<?php
use App\Core\Auth;
use App\Core\Database;

$onHand = '(SELECT COALESCE(SUM(b.qty_remaining),0) FROM stock_batches b WHERE b.item_id = t.id)';
$value = '(SELECT COALESCE(SUM(b.qty_remaining * b.unit_cost),0) FROM stock_batches b WHERE b.item_id = t.id)';
$reserved = "(SELECT COALESCE(SUM(l.qty),0) FROM shop_order_lines l JOIN shop_orders o ON o.id = l.order_id WHERE l.item_id = t.id AND o.status IN ('placed','confirmed','packed') AND o.invoice_id IS NULL)";

return [
    'title' => 'Stock Summary',
    'singular' => 'Item',
    'icon' => 'boxes',
    'description' => 'Quantity on hand from FIFO batches, quantity reserved by online orders, and stock value at cost.',
    'table' => 'items',
    'from' => 'items t LEFT JOIN item_categories c ON c.id = t.category_id LEFT JOIN units u ON u.id = t.unit_id',
    'where' => static fn () => ['t.track_inventory = 1', []],
    'readonly' => true, 'can_create' => false, 'can_delete' => false,
    'row_url' => '/inventory/stock-moves?f[item_id]={id}',
    'columns' => [
        'sku' => ['label' => 'SKU', 'sql' => 't.sku', 'format' => 'code'],
        'name' => ['label' => 'Item', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['name']) . '</span>' . ($r['publish_online'] ? ' <span class="badge badge-violet ml-1">Online</span>' : '')],
        'category' => ['label' => 'Category', 'sql' => 'c.name'],
        'on_hand' => ['label' => 'On hand', 'sql' => $onHand, 'align' => 'right', 'render' => static fn ($r) => '<span class="tabular-nums font-semibold ' . ((float) $r['on_hand'] <= (float) $r['reorder_level'] ? 'text-rose-600' : 'text-slate-900') . '">' . e(rtrim(rtrim(number_format((float) $r['on_hand'], 3), '0'), '.') ?: '0') . '</span> <span class="text-xs text-slate-400">' . e($r['unit']) . '</span>'],
        'reserved' => ['label' => 'Reserved (online)', 'sql' => $reserved, 'format' => 'number', 'align' => 'right'],
        'available' => ['label' => 'Available', 'sql' => "($onHand - $reserved)", 'format' => 'number', 'align' => 'right'],
        'online_cap' => ['label' => 'Online cap', 'sql' => 't.online_stock_cap', 'format' => 'number', 'align' => 'right'],
        'reorder_level' => ['label' => 'Re-order at', 'sql' => 't.reorder_level', 'format' => 'number', 'align' => 'right'],
        'avg_cost' => ['label' => 'Avg cost', 'sql' => "CASE WHEN $onHand > 0 THEN $value / $onHand END", 'format' => 'money', 'align' => 'right'],
        'value' => ['label' => 'Stock value', 'sql' => $value, 'format' => 'money', 'align' => 'right'],
    ],
    'select_extra' => ['publish_online' => 't.publish_online', 'unit' => 'u.code'],
    'default_sort' => ['name', 'asc'],
    'search' => ['t.sku', 't.name', 't.barcode'],
    'filters' => [
        'category_id' => ['label' => 'Category', 'type' => 'select', 'dropdown' => true, 'sql' => 't.category_id', 'options' => static fn () => array_column(Database::all('SELECT id, name FROM item_categories WHERE company_id = ? ORDER BY name', [Auth::instance()->companyId()]), 'name', 'id')],
        'level' => ['label' => 'Stock level', 'type' => 'select', 'sql' => "(CASE WHEN $onHand <= 0 THEN 'out' WHEN $onHand <= t.reorder_level THEN 'low' ELSE 'ok' END)", 'options' => ['out' => 'Out of stock', 'low' => 'At / below re-order level', 'ok' => 'Healthy']],
        'publish_online' => ['label' => 'Published online', 'type' => 'select', 'sql' => 't.publish_online', 'options' => ['1' => 'Yes', '0' => 'No']],
    ],
    'stats' => [
        ['label' => 'Stock items', 'sql' => 'COUNT(*)', 'icon' => 'boxes'],
        ['label' => 'Stock value (cost)', 'sql' => "COALESCE(SUM($value),0)", 'format' => 'money', 'icon' => 'wallet', 'tone' => 'emerald'],
        ['label' => 'At / below re-order', 'sql' => "COALESCE(SUM($onHand <= t.reorder_level),0)", 'icon' => 'triangle-alert', 'tone' => 'amber'],
        ['label' => 'Reserved by web orders', 'sql' => "COALESCE(SUM($reserved > 0),0)", 'icon' => 'shopping-cart', 'tone' => 'violet'],
    ],
    'fields' => [],
];
