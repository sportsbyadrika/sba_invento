<?php
use App\Core\Auth;
use App\Core\Database;

$cats = ['standard' => ['Standard', 'cyan'], 'zero' => ['Zero rated', 'emerald'], 'exempt' => ['Exempt', 'slate'], 'reverse_charge' => ['Reverse charge', 'amber'], 'out_of_scope' => ['Out of scope', 'slate']];
$taxLedgers = static fn () => array_column(Database::all("SELECT id, CONCAT(code, ' · ', name) label FROM accounts WHERE company_id = ? AND is_group = 0 AND account_type IN ('liability','asset') ORDER BY code", [Auth::instance()->companyId()]), 'label', 'id');
$regime = Auth::instance()->company()['tax_regime'] ?? 'NONE';

return [
    'title' => 'Tax Rates',
    'singular' => 'Tax Rate',
    'icon' => 'percent',
    'admin_only' => true,
    'description' => $regime === 'IN_GST'
        ? 'GST rates. CGST + SGST (intra-state) or IGST (inter-state) is decided on each document from the place of supply.'
        : ($regime === 'UAE_VAT' ? 'UAE VAT rates (standard 5%, zero rated, exempt, reverse charge).' : 'Tax rates used on documents.'),
    'table' => 'tax_rates',
    'from' => 'tax_rates t LEFT JOIN accounts o ON o.id = t.output_account_id LEFT JOIN accounts i ON i.id = t.input_account_id',
    'columns' => [
        'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code'],
        'name' => ['label' => 'Name', 'sql' => 't.name', 'render' => static fn ($r) => e($r['name']) . ($r['is_default'] ? ' <span class="badge badge-cyan ml-1">Default</span>' : '')],
        'tax_type' => ['label' => 'Type', 'sql' => 't.tax_type'],
        'category' => ['label' => 'Category', 'sql' => 't.category', 'format' => 'badge', 'map' => $cats],
        'rate' => ['label' => 'Rate %', 'sql' => 't.rate', 'format' => 'number', 'align' => 'right'],
        'output' => ['label' => 'Output Account', 'sql' => 'o.name'],
        'input' => ['label' => 'Input Account', 'sql' => 'i.name'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'select_extra' => ['is_default' => 't.is_default'],
    'default_sort' => ['rate', 'asc'],
    'search' => ['t.code', 't.name'],
    'filters' => [
        'category' => ['label' => 'Category', 'type' => 'select', 'sql' => 't.category', 'options' => array_map(static fn ($c) => $c[0], $cats)],
        'is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']],
    ],
    'stats' => [['label' => 'Tax Rates', 'sql' => 'COUNT(*)', 'icon' => 'percent'], ['label' => 'Active', 'sql' => 'COALESCE(SUM(t.is_active),0)', 'icon' => 'circle-check', 'tone' => 'emerald']],
    'fields' => [
        'name' => ['label' => 'Name', 'required' => true, 'max' => 60],
        'code' => ['label' => 'Code', 'required' => true, 'max' => 20, 'unique' => true, 'uppercase' => true],
        'tax_type' => ['label' => 'Tax Type', 'type' => 'select', 'options' => ['VAT' => 'VAT', 'GST' => 'GST', 'CESS' => 'Cess', 'OTHER' => 'Other'], 'default' => $regime === 'IN_GST' ? 'GST' : 'VAT'],
        'category' => ['label' => 'Category', 'type' => 'select', 'options' => array_map(static fn ($c) => $c[0], $cats), 'default' => 'standard'],
        'rate' => ['label' => 'Rate %', 'type' => 'decimal', 'required' => true, 'min' => 0],
        'output_account_id' => ['label' => 'Output (sales) tax account', 'type' => 'lookup', 'options' => $taxLedgers],
        'input_account_id' => ['label' => 'Input (purchase) tax account', 'type' => 'lookup', 'options' => $taxLedgers],
        'is_default' => ['label' => 'Default rate for new items', 'type' => 'checkbox'],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'before_save' => static function (array $d): array {
            $d['output_account_id'] = ($d['output_account_id'] ?? '') ?: null;
            $d['input_account_id'] = ($d['input_account_id'] ?? '') ?: null;
            return $d;
        },
        'after_save' => static function (int $id, array $d): void {
            if (!empty($d['is_default'])) {
                Database::query('UPDATE tax_rates SET is_default = 0 WHERE company_id = ? AND id <> ?', [Auth::instance()->companyId(), $id]);
            }
        },
        'before_delete' => static fn ($r) => Database::value('SELECT COUNT(*) FROM items WHERE tax_rate_id = ?', [$r['id']]) ? 'Items use this tax rate. Mark it inactive instead.' : null,
    ],
];
