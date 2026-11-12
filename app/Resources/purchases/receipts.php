<?php
use App\Core\Auth;
use App\Core\Database;

return [
    'title' => 'Goods Receipts',
    'singular' => 'Receipt',
    'icon' => 'package-check',
    'description' => 'Stock batches received against purchase orders. Unit cost includes the freight share (landed cost).',
    'table' => 'stock_batches',
    'from' => "stock_batches t JOIN items i ON i.id = t.item_id LEFT JOIN documents d ON d.id = t.source_id AND t.source_type = 'receipt' LEFT JOIN contacts c ON c.id = d.contact_id",
    'scope' => ['source_type' => 'receipt'],
    'readonly' => true, 'can_create' => false, 'can_delete' => false,
    'row_url' => '/purchases/orders/{po_id}',
    'columns' => [
        'received_at' => ['label' => 'Received', 'sql' => 't.received_at', 'format' => 'date'],
        'po' => ['label' => 'Purchase order', 'sql' => 'd.number', 'format' => 'code'],
        'vendor' => ['label' => 'Vendor', 'sql' => 'c.name'],
        'item' => ['label' => 'Item', 'sql' => "CONCAT(i.sku, ' · ', i.name)"],
        'qty_in' => ['label' => 'Received', 'sql' => 't.qty_in', 'format' => 'number', 'align' => 'right'],
        'qty_remaining' => ['label' => 'Remaining', 'sql' => 't.qty_remaining', 'format' => 'number', 'align' => 'right'],
        'unit_cost' => ['label' => 'Landed unit cost', 'sql' => 't.unit_cost', 'format' => 'money', 'align' => 'right'],
        'value' => ['label' => 'Remaining value', 'sql' => 't.qty_remaining * t.unit_cost', 'format' => 'money', 'align' => 'right'],
    ],
    'default_sort' => ['received_at', 'desc'],
    'search' => ['d.number', 'i.sku', 'i.name', 'c.name'],
    'filters' => [
        'date' => ['label' => 'Received', 'type' => 'daterange', 'sql' => 't.received_at'],
        'item_id' => ['label' => 'Item', 'type' => 'select', 'dropdown' => true, 'sql' => 't.item_id', 'options' => static fn () => array_column(Database::all("SELECT id, CONCAT(sku, ' · ', name) n FROM items WHERE company_id = ? AND track_inventory = 1 ORDER BY name", [Auth::instance()->companyId()]), 'n', 'id')],
        'open' => ['label' => 'Batch', 'type' => 'select', 'sql' => '(t.qty_remaining > 0)', 'options' => ['1' => 'Still in stock', '0' => 'Fully used']],
    ],
    'stats' => [
        ['label' => 'Batches', 'sql' => 'COUNT(*)', 'icon' => 'package-check'],
        ['label' => 'Units received', 'sql' => 'COALESCE(SUM(t.qty_in),0)', 'icon' => 'arrow-down', 'tone' => 'emerald'],
        ['label' => 'Value still in stock', 'sql' => 'COALESCE(SUM(t.qty_remaining * t.unit_cost),0)', 'format' => 'money', 'icon' => 'wallet', 'tone' => 'cyan'],
    ],
    'select_extra' => ['po_id' => 't.source_id'],
    'fields' => [],
];
