<?php
use App\Core\Auth;
use App\Core\Database;

$cid = static fn () => Auth::instance()->companyId();
$opt = static fn (string $sql) => static fn () => array_column(Database::all($sql, [$cid()]), 'label', 'id');
$ledgers = static fn (array $types) => static fn () => array_column(Database::all(
    "SELECT id, CONCAT(code, ' · ', name) label FROM accounts WHERE company_id = ? AND is_group = 0 AND is_active = 1 AND account_type IN ('" . implode("','", $types) . "') ORDER BY code",
    [$cid()]
), 'label', 'id');
$regime = Auth::instance()->company()['tax_regime'] ?? 'NONE';

return [
    'title' => 'Items',
    'singular' => 'Item',
    'icon' => 'package',
    'description' => 'Goods and services you buy and sell, with prices, tax and the accounts they post to.',
    'table' => 'items',
    'from' => 'items t LEFT JOIN item_categories c ON c.id = t.category_id LEFT JOIN units u ON u.id = t.unit_id LEFT JOIN tax_rates tx ON tx.id = t.tax_rate_id',
    'creator' => true,
    'drawer_width' => 'max-w-3xl',
    'columns' => [
        'sku' => ['label' => 'SKU', 'sql' => 't.sku', 'format' => 'code'],
        'name' => ['label' => 'Item Name', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['name']) . '</span>' . ($r['barcode'] ? '<span class="block font-mono text-[11px] text-slate-400">' . e($r['barcode']) . '</span>' : '')],
        'item_type' => ['label' => 'Type', 'sql' => 't.item_type', 'format' => 'badge', 'map' => ['goods' => ['Goods', 'cyan'], 'service' => ['Service', 'violet']]],
        'category' => ['label' => 'Category', 'sql' => 'c.name'],
        'unit' => ['label' => 'Unit', 'sql' => 'u.code', 'format' => 'code'],
        'hsn_sac' => ['label' => $regime === 'IN_GST' ? 'HSN/SAC' : 'HS Code', 'sql' => 't.hsn_sac', 'format' => 'code'],
        'tax' => ['label' => 'Tax', 'sql' => 'tx.name'],
        'sales_price' => ['label' => 'Selling Price', 'sql' => 't.sales_price', 'format' => 'money', 'align' => 'right'],
        'purchase_price' => ['label' => 'Cost Price', 'sql' => 't.purchase_price', 'format' => 'money', 'align' => 'right'],
        'margin' => ['label' => 'Margin %', 'sql' => 'CASE WHEN t.sales_price > 0 THEN ROUND((t.sales_price - t.purchase_price) / t.sales_price * 100, 1) END', 'align' => 'right', 'render' => static fn ($r) => $r['margin'] === null ? '<span class="text-slate-400">—</span>' : '<span class="tabular-nums ' . ($r['margin'] < 0 ? 'text-rose-600' : ($r['margin'] < 10 ? 'text-amber-600' : 'text-emerald-700')) . '">' . e($r['margin']) . '%</span>'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'select_extra' => ['barcode' => 't.barcode'],
    'default_sort' => ['name', 'asc'],
    'search' => ['t.sku', 't.name', 't.barcode', 't.hsn_sac', 'c.name'],
    'filters' => [
        'item_type' => ['label' => 'Item Type', 'type' => 'select', 'sql' => 't.item_type', 'options' => ['goods' => 'Goods', 'service' => 'Service']],
        'category_id' => ['label' => 'Category', 'type' => 'select', 'sql' => 't.category_id', 'options' => $opt('SELECT id, name label FROM item_categories WHERE company_id = ? ORDER BY name')],
        'tax_rate_id' => ['label' => 'Tax Rate', 'type' => 'select', 'sql' => 't.tax_rate_id', 'options' => $opt('SELECT id, name label FROM tax_rates WHERE company_id = ? ORDER BY rate')],
        'unit_id' => ['label' => 'Unit', 'type' => 'select', 'sql' => 't.unit_id', 'options' => $opt('SELECT id, name label FROM units WHERE company_id = ? ORDER BY name')],
        'sales_price' => ['label' => 'Selling Price', 'type' => 'numrange', 'sql' => 't.sales_price'],
        'low_margin' => ['label' => 'Margin', 'type' => 'select', 'sql' => "(CASE WHEN t.sales_price <= 0 THEN 'na' WHEN (t.sales_price - t.purchase_price) / t.sales_price < 0 THEN 'loss' WHEN (t.sales_price - t.purchase_price) / t.sales_price < 0.1 THEN 'low' ELSE 'ok' END)", 'options' => ['loss' => 'Below cost', 'low' => 'Under 10%', 'ok' => '10% and above', 'na' => 'No selling price']],
        'is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']],
    ],
    'stats' => [
        ['label' => 'Items', 'sql' => 'COUNT(*)', 'icon' => 'package', 'tone' => 'slate'],
        ['label' => 'Goods / Services', 'sql' => "CONCAT(COALESCE(SUM(t.item_type='goods'),0), ' / ', COALESCE(SUM(t.item_type='service'),0))", 'format' => 'text', 'icon' => 'shapes', 'tone' => 'cyan'],
        ['label' => 'Sold below cost', 'sql' => 'COALESCE(SUM(t.sales_price > 0 AND t.sales_price < t.purchase_price),0)', 'icon' => 'triangle-alert', 'tone' => 'rose'],
        ['label' => 'Avg. Margin %', 'sql' => 'COALESCE(ROUND(AVG(CASE WHEN t.sales_price > 0 THEN (t.sales_price - t.purchase_price) / t.sales_price * 100 END), 1), 0)', 'format' => 'text', 'icon' => 'percent', 'tone' => 'emerald'],
    ],
    'fields' => [
        'item_type' => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => ['goods' => 'Goods (stock item)', 'service' => 'Service'], 'default' => 'goods'],
        'sku' => ['label' => 'SKU / Item Code', 'required' => true, 'max' => 40, 'unique' => true, 'uppercase' => true],
        'name' => ['label' => 'Item Name', 'required' => true, 'max' => 150, 'col' => 2],
        'category_id' => ['label' => 'Category', 'type' => 'lookup', 'options' => $opt('SELECT id, name label FROM item_categories WHERE company_id = ? AND is_active = 1 ORDER BY name')],
        'unit_id' => ['label' => 'Unit of Measure', 'type' => 'lookup', 'options' => $opt("SELECT id, CONCAT(code, ' - ', name) label FROM units WHERE company_id = ? AND is_active = 1 ORDER BY code")],
        'hsn_sac' => ['label' => $regime === 'IN_GST' ? 'HSN / SAC Code' : 'HS Code', 'max' => 12, 'pattern' => '/^\d{4,10}$/', 'pattern_message' => 'Enter 4 to 10 digits.'],
        'barcode' => ['label' => 'Barcode', 'max' => 60],
        'section_price' => ['label' => 'Pricing & tax', 'type' => 'section'],
        'sales_price' => ['label' => 'Selling Price', 'type' => 'decimal', 'min' => 0],
        'purchase_price' => ['label' => 'Cost Price', 'type' => 'decimal', 'min' => 0],
        'tax_rate_id' => ['label' => 'Tax Rate', 'type' => 'lookup', 'options' => $opt('SELECT id, name label FROM tax_rates WHERE company_id = ? AND is_active = 1 ORDER BY rate')],
        'section_acc' => ['label' => 'Accounting', 'type' => 'section'],
        'sales_account_id' => ['label' => 'Sales Account', 'type' => 'lookup', 'options' => $ledgers(['income'])],
        'purchase_account_id' => ['label' => 'Purchase / Expense Account', 'type' => 'lookup', 'options' => $ledgers(['expense', 'asset'])],
        'inventory_account_id' => ['label' => 'Inventory Account', 'type' => 'lookup', 'options' => $ledgers(['asset'])],
        'section_stock' => ['label' => 'Stock', 'type' => 'section'],
        'track_inventory' => ['label' => 'Track stock quantity', 'type' => 'checkbox', 'default' => 1],
        'reorder_level' => ['label' => 'Re-order Level', 'type' => 'decimal', 'min' => 0],
        'opening_stock' => ['label' => 'Opening Stock (qty)', 'type' => 'decimal', 'min' => 0],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 500, 'col' => 2],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'before_save' => static function (array $d): array {
            if (($d['item_type'] ?? '') === 'service') {
                $d['track_inventory'] = 0;
                $d['inventory_account_id'] = null;
            }
            foreach (['category_id', 'unit_id', 'tax_rate_id', 'sales_account_id', 'purchase_account_id', 'inventory_account_id'] as $k) {
                $d[$k] = ($d[$k] ?? '') ?: null;
            }
            return $d;
        },
    ],
];
