<?php
/** Shared definition for customers and vendors (one `contacts` table). */
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Services\NumberSeries;
use App\Services\TaxRules;

return static function (string $type): array {
    $isCustomer = $type === 'customer';
    $company = Auth::instance()->company() ?? [];
    $regime = $company['tax_regime'] ?? 'NONE';
    $treatments = TaxRules::TREATMENTS[$regime] ?? TaxRules::TREATMENTS['NONE'];
    $places = TaxRules::places($regime);
    $taxLabel = ['UAE_VAT' => 'TRN', 'IN_GST' => 'GSTIN'][$regime] ?? 'Tax No.';
    $cid = static fn () => Auth::instance()->companyId();
    $balanceSql = '(t.opening_balance)';

    return [
        'title' => $isCustomer ? 'Customers' : 'Vendors',
        'singular' => $isCustomer ? 'Customer' : 'Vendor',
        'icon' => $isCustomer ? 'users' : 'store',
        'description' => $isCustomer ? 'People and businesses you sell to.' : 'Suppliers you purchase goods and services from.',
        'table' => 'contacts',
        'scope' => ['contact_type' => $type],
        'from' => 'contacts t',
        'creator' => true,
        'columns' => [
            'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code', 'width' => 'w-24'],
            'name' => ['label' => 'Name', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['name']) . '</span>' . ($r['contact_person'] ? '<span class="block text-xs text-slate-500">' . e($r['contact_person']) . '</span>' : '')],
            'email' => ['label' => 'E-mail', 'sql' => 't.email'],
            'phone' => ['label' => 'Phone', 'sql' => 't.phone'],
            'tax_number' => ['label' => $taxLabel, 'sql' => 't.tax_number', 'format' => 'code'],
            'place_of_supply' => ['label' => $regime === 'IN_GST' ? 'State' : 'Emirate', 'sql' => 't.place_of_supply', 'render' => static fn ($r) => e($places[$r['place_of_supply']] ?? $r['place_of_supply'])],
            'currency_code' => ['label' => 'Cur', 'sql' => 't.currency_code', 'format' => 'code'],
            'credit_limit' => ['label' => 'Credit Limit', 'sql' => 't.credit_limit', 'format' => 'money', 'align' => 'right'],
            'balance' => ['label' => 'Opening Bal.', 'sql' => $balanceSql, 'format' => 'money', 'align' => 'right'],
            'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
        ],
        'select_extra' => ['contact_person' => 't.contact_person'],
        'default_sort' => ['name', 'asc'],
        'search' => ['t.code', 't.name', 't.email', 't.phone', 't.tax_number', 't.contact_person'],
        'filters' => [
            'tax_treatment' => ['label' => 'Tax Treatment', 'type' => 'select', 'sql' => 't.tax_treatment', 'options' => $treatments],
            'place_of_supply' => ['label' => $regime === 'IN_GST' ? 'State' : 'Emirate', 'type' => 'select', 'sql' => 't.place_of_supply', 'options' => $places],
            'currency_code' => ['label' => 'Currency', 'type' => 'select', 'sql' => 't.currency_code', 'options' => static fn () => array_column(Database::all('SELECT code FROM currencies WHERE company_id = ? ORDER BY code', [$cid()]), 'code', 'code')],
            'registered' => ['label' => "Has $taxLabel", 'type' => 'select', 'sql' => "(t.tax_number IS NOT NULL AND t.tax_number <> '')", 'options' => ['1' => 'Yes', '0' => 'No']],
            'is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']],
            'credit' => ['label' => 'Credit Limit', 'type' => 'numrange', 'sql' => 't.credit_limit'],
            'created' => ['label' => 'Created On', 'type' => 'daterange', 'sql' => 't.created_at'],
            'city' => ['label' => 'Address contains', 'type' => 'text', 'sql' => 't.billing_address'],
        ],
        'stats' => [
            ['label' => $isCustomer ? 'Customers' : 'Vendors', 'sql' => 'COUNT(*)', 'icon' => $isCustomer ? 'users' : 'store', 'tone' => 'slate'],
            ['label' => "With $taxLabel", 'sql' => "COALESCE(SUM(t.tax_number IS NOT NULL AND t.tax_number <> ''),0)", 'icon' => 'badge-check', 'tone' => 'emerald'],
            ['label' => 'Foreign Currency', 'sql' => 'COALESCE(SUM(t.currency_code IS NOT NULL AND t.currency_code <> ' . Database::pdo()->quote($company['base_currency'] ?? 'AED') . '),0)', 'icon' => 'globe', 'tone' => 'indigo'],
            ['label' => 'Opening Balance', 'sql' => "COALESCE(SUM($balanceSql),0)", 'format' => 'money', 'icon' => 'wallet', 'tone' => 'amber'],
        ],
        'fields' => [
            'code' => ['label' => 'Code', 'max' => 20, 'unique' => true, 'placeholder' => 'Auto', 'help' => 'Leave empty to auto-number.', 'uppercase' => true],
            'name' => ['label' => $isCustomer ? 'Customer Name' : 'Vendor Name', 'required' => true, 'max' => 150],
            'contact_person' => ['label' => 'Contact Person', 'max' => 100],
            'email' => ['label' => 'E-mail', 'type' => 'email', 'max' => 150],
            'phone' => ['label' => 'Phone', 'max' => 40],
            'currency_code' => ['label' => 'Currency', 'type' => 'lookup', 'default' => $company['base_currency'] ?? null, 'options' => static fn () => array_column(Database::all('SELECT code, CONCAT(code, " - ", name) label FROM currencies WHERE company_id = ? AND is_active = 1 ORDER BY is_base DESC, code', [$cid()]), 'label', 'code')],
            'section_tax' => ['label' => 'Tax details', 'type' => 'section'],
            'tax_treatment' => ['label' => 'Tax Treatment', 'type' => 'select', 'options' => ['' => '—'] + $treatments],
            'tax_number' => ['label' => $taxLabel, 'max' => 30, 'uppercase' => true],
            'place_of_supply' => ['label' => $regime === 'IN_GST' ? 'Place of Supply (State)' : 'Emirate', 'type' => 'select', 'options' => ['' => '—'] + $places],
            'section_terms' => ['label' => 'Terms & balances', 'type' => 'section'],
            'payment_terms' => ['label' => 'Payment Terms (days)', 'type' => 'number', 'min' => 0, 'default' => 0],
            'credit_limit' => ['label' => 'Credit Limit', 'type' => 'decimal', 'min' => 0],
            'opening_balance' => ['label' => 'Opening Balance', 'type' => 'decimal', 'help' => $isCustomer ? 'Amount the customer owes you.' : 'Amount you owe the vendor.'],
            'billing_address' => ['label' => 'Billing Address', 'type' => 'textarea', 'max' => 1000],
            'shipping_address' => ['label' => 'Shipping Address', 'type' => 'textarea', 'max' => 1000],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
        ],
        'hooks' => [
            'before_save' => static function (array $d, ?array $existing) use ($regime, $type): array {
                if (($d['code'] ?? '') === '' || $d['code'] === null) {
                    if ($existing) {
                        throw new ValidationException(['code' => 'Code is required.']);
                    }
                    $d['code'] = NumberSeries::next((int) Auth::instance()->companyId(), $type);
                }
                if ($err = TaxRules::validateTaxNumber($regime, (string) ($d['tax_number'] ?? ''), $regime === 'IN_GST' ? (string) ($d['place_of_supply'] ?? '') : '')) {
                    throw new ValidationException(['tax_number' => $err]);
                }
                if (in_array($d['tax_treatment'] ?? '', ['vat_registered', 'gcc_registered', 'registered', 'composition', 'sez'], true) && empty($d['tax_number'])) {
                    throw new ValidationException(['tax_number' => 'A tax registration number is required for this tax treatment.']);
                }
                foreach (['tax_treatment', 'place_of_supply', 'currency_code'] as $k) {
                    $d[$k] = ($d[$k] ?? '') ?: null;
                }
                return $d;
            },
        ],
    ];
};
