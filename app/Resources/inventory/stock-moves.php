<?php
use App\Core\Auth;
use App\Core\Database;

$types = ['opening' => ['Opening', 'slate'], 'receipt' => ['Purchase receipt', 'green'], 'invoice' => ['Sale', 'blue'], 'return' => ['Customer return', 'violet'],
    'invoice_void' => ['Sale reversed', 'amber'], 'adjustment' => ['Adjustment', 'amber']];

return [
    'title' => 'Stock Ledger',
    'singular' => 'Stock move',
    'icon' => 'arrow-left-right',
    'description' => 'Every quantity in and out, with the FIFO batch and unit cost used.',
    'table' => 'stock_moves',
    'from' => 'stock_moves t JOIN items i ON i.id = t.item_id LEFT JOIN stock_batches b ON b.id = t.batch_id',
    'readonly' => true, 'can_create' => false, 'can_delete' => false,
    'columns' => [
        'move_date' => ['label' => 'Date', 'sql' => 't.move_date', 'format' => 'date'],
        'item' => ['label' => 'Item', 'sql' => "CONCAT(i.sku, ' · ', i.name)"],
        'source_type' => ['label' => 'Type', 'sql' => 't.source_type', 'format' => 'badge', 'map' => $types],
        'reference' => ['label' => 'Reference', 'sql' => 't.reference', 'format' => 'code'],
        'batch' => ['label' => 'Batch', 'sql' => "CONCAT('#', t.batch_id, ' · ', DATE_FORMAT(b.received_at, '%d-%m-%Y'))"],
        'qty' => ['label' => 'Qty', 'sql' => 't.qty', 'align' => 'right', 'render' => static fn ($r) => '<span class="tabular-nums font-semibold ' . ((float) $r['qty'] < 0 ? 'text-rose-600' : 'text-emerald-700') . '">' . ((float) $r['qty'] > 0 ? '+' : '') . e(rtrim(rtrim(number_format((float) $r['qty'], 3), '0'), '.')) . '</span>'],
        'unit_cost' => ['label' => 'Unit cost', 'sql' => 't.unit_cost', 'format' => 'money', 'align' => 'right'],
        'value' => ['label' => 'Value', 'sql' => 't.value', 'format' => 'money', 'align' => 'right'],
    ],
    'default_sort' => ['move_date', 'desc'],
    'search' => ['i.sku', 'i.name', 't.reference'],
    'filters' => [
        'item_id' => ['label' => 'Item', 'type' => 'select', 'dropdown' => true, 'sql' => 't.item_id', 'options' => static fn () => array_column(Database::all("SELECT id, CONCAT(sku, ' · ', name) n FROM items WHERE company_id = ? AND track_inventory = 1 ORDER BY name", [Auth::instance()->companyId()]), 'n', 'id')],
        'source_type' => ['label' => 'Type', 'type' => 'select', 'sql' => 't.source_type', 'options' => array_map(static fn ($t) => $t[0], $types)],
        'date' => ['label' => 'Date', 'type' => 'daterange', 'sql' => 't.move_date'],
        'direction' => ['label' => 'Direction', 'type' => 'select', 'sql' => "(CASE WHEN t.qty > 0 THEN 'in' ELSE 'out' END)", 'options' => ['in' => 'In', 'out' => 'Out']],
    ],
    'stats' => [
        ['label' => 'Moves', 'sql' => 'COUNT(*)', 'icon' => 'arrow-left-right'],
        ['label' => 'Qty in', 'sql' => 'COALESCE(SUM(CASE WHEN t.qty > 0 THEN t.qty END),0)', 'icon' => 'arrow-down', 'tone' => 'emerald'],
        ['label' => 'Qty out', 'sql' => 'COALESCE(-SUM(CASE WHEN t.qty < 0 THEN t.qty END),0)', 'icon' => 'arrow-up', 'tone' => 'rose'],
        ['label' => 'Net value', 'sql' => 'COALESCE(SUM(t.value),0)', 'format' => 'money', 'icon' => 'wallet', 'tone' => 'cyan'],
    ],
    'fields' => [],
];
