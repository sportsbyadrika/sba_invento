<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Seeds a new company with a ready-to-use chart of accounts, currencies,
 * taxes (UAE VAT / India GST), units, a default warehouse and number series.
 */
final class CompanyProvisioner
{
    public const CURRENCIES = [
        'AED' => ['UAE Dirham', 'AED', 2], 'INR' => ['Indian Rupee', '₹', 2], 'USD' => ['US Dollar', '$', 2],
        'EUR' => ['Euro', '€', 2], 'GBP' => ['Pound Sterling', '£', 2], 'SAR' => ['Saudi Riyal', 'SAR', 2],
        'QAR' => ['Qatari Riyal', 'QAR', 2], 'OMR' => ['Omani Rial', 'OMR', 3], 'KWD' => ['Kuwaiti Dinar', 'KWD', 3],
        'BHD' => ['Bahraini Dinar', 'BHD', 3],
    ];

    /** Approximate starting rates vs USD - the company admin must maintain real rates. */
    private const USD_RATE = ['AED' => 3.6725, 'INR' => 88.0, 'USD' => 1, 'EUR' => 0.86, 'GBP' => 0.76, 'SAR' => 3.75, 'QAR' => 3.64, 'OMR' => 0.3845, 'KWD' => 0.307, 'BHD' => 0.376];

    public static function provision(int $companyId): void
    {
        $company = Database::one('SELECT * FROM companies WHERE id = ?', [$companyId]);
        if (!$company) {
            return;
        }
        self::currencies($company);
        $acc = self::chartOfAccounts($company);
        self::taxes($company, $acc);
        foreach ([['NOS', 'Numbers', 'NOS', 0], ['PCS', 'Pieces', 'PCS', 0], ['BOX', 'Box', 'BOX', 0], ['KG', 'Kilogram', 'KGS', 3], ['LTR', 'Litre', 'LTR', 3], ['MTR', 'Metre', 'MTR', 2], ['HRS', 'Hours', 'OTH', 2]] as [$code, $name, $uqc, $dec]) {
            Database::query('INSERT IGNORE INTO units (company_id, code, name, uqc, decimals) VALUES (?,?,?,?,?)', [$companyId, $code, $name, $uqc, $dec]);
        }
        Database::query("INSERT IGNORE INTO warehouses (company_id, code, name, is_default) VALUES (?, 'MAIN', 'Main Warehouse', 1)", [$companyId]);
        foreach (NumberSeries::DEFAULTS as $type => $prefix) {
            Database::query('INSERT IGNORE INTO number_series (company_id, doc_type, prefix, next_number, padding) VALUES (?,?,?,1,?)', [$companyId, $type, $prefix, in_array($type, ['customer', 'vendor'], true) ? 4 : 5]);
        }
    }

    private static function currencies(array $company): void
    {
        $base = $company['base_currency'];
        $list = self::CURRENCIES;
        if (!isset($list[$base])) {
            $list[$base] = [$base, $base, 2];
        }
        $baseUsd = self::USD_RATE[$base] ?? 1;
        foreach ($list as $code => [$name, $symbol, $dec]) {
            $rate = $code === $base ? 1 : round($baseUsd / (self::USD_RATE[$code] ?? 1), 8);
            Database::query(
                'INSERT IGNORE INTO currencies (company_id, code, name, symbol, decimal_places, exchange_rate, is_base, is_active) VALUES (?,?,?,?,?,?,?,?)',
                [$company['id'], $code, $name, $symbol, $dec, $rate, $code === $base ? 1 : 0, in_array($code, [$base, 'USD', 'EUR', 'AED', 'INR'], true) ? 1 : 0]
            );
        }
    }

    /** @return array<string,int> code => account id */
    private static function chartOfAccounts(array $company): array
    {
        $regime = $company['tax_regime'];
        // [code, name, type, parentCode|null, isGroup, subType]
        $tree = [
            ['1000', 'Assets', 'asset', null, 1, null],
            ['1100', 'Current Assets', 'asset', '1000', 1, null],
            ['1110', 'Cash in Hand', 'asset', '1100', 0, 'cash'],
            ['1120', 'Bank Accounts', 'asset', '1100', 1, 'bank'],
            ['1130', 'Accounts Receivable', 'asset', '1100', 0, 'receivable'],
            ['1140', 'Inventory / Stock in Hand', 'asset', '1100', 0, 'stock'],
            ['1150', 'Prepaid Expenses', 'asset', '1100', 0, null],
            ['1160', 'Advances & Deposits', 'asset', '1100', 0, null],
            ['1200', 'Fixed Assets', 'asset', '1000', 1, null],
            ['1210', 'Furniture & Fixtures', 'asset', '1200', 0, 'fixed'],
            ['1220', 'Computers & IT Equipment', 'asset', '1200', 0, 'fixed'],
            ['1230', 'Vehicles', 'asset', '1200', 0, 'fixed'],
            ['1290', 'Accumulated Depreciation', 'asset', '1200', 0, 'fixed'],
            ['2000', 'Liabilities', 'liability', null, 1, null],
            ['2100', 'Current Liabilities', 'liability', '2000', 1, null],
            ['2110', 'Accounts Payable', 'liability', '2100', 0, 'payable'],
            ['2120', 'Accrued Expenses', 'liability', '2100', 0, null],
            ['2130', 'Duties & Taxes', 'liability', '2100', 1, 'tax'],
            ['2140', 'Salaries Payable', 'liability', '2100', 0, null],
            ['2200', 'Long Term Liabilities', 'liability', '2000', 1, null],
            ['2210', 'Bank Loans', 'liability', '2200', 0, null],
            ['3000', 'Equity', 'equity', null, 1, null],
            ['3100', 'Owner\'s Capital', 'equity', '3000', 0, null],
            ['3200', 'Retained Earnings', 'equity', '3000', 0, 'retained'],
            ['3300', 'Drawings', 'equity', '3000', 0, null],
            ['3900', 'Opening Balance Difference', 'equity', '3000', 0, 'opening'],
            ['4000', 'Income', 'income', null, 1, null],
            ['4100', 'Sales', 'income', '4000', 0, 'sales'],
            ['4200', 'Service Income', 'income', '4000', 0, 'sales'],
            ['4300', 'Other Income', 'income', '4000', 0, null],
            ['4400', 'Discount Received', 'income', '4000', 0, null],
            ['4500', 'Exchange Gain / Loss', 'income', '4000', 0, 'fx'],
            ['5000', 'Expenses', 'expense', null, 1, null],
            ['5100', 'Cost of Goods Sold', 'expense', '5000', 0, 'cogs'],
            ['5200', 'Purchases', 'expense', '5000', 0, 'purchase'],
            ['5300', 'Operating Expenses', 'expense', '5000', 1, null],
            ['5310', 'Salaries & Wages', 'expense', '5300', 0, null],
            ['5320', 'Rent', 'expense', '5300', 0, null],
            ['5330', 'Utilities', 'expense', '5300', 0, null],
            ['5340', 'Office Supplies', 'expense', '5300', 0, null],
            ['5350', 'Bank Charges', 'expense', '5300', 0, null],
            ['5360', 'Depreciation', 'expense', '5300', 0, null],
            ['5370', 'Discount Allowed', 'expense', '5300', 0, null],
        ];
        if ($regime === 'UAE_VAT') {
            $tree[] = ['2131', 'VAT Output (Payable)', 'liability', '2130', 0, 'tax_output'];
            $tree[] = ['2132', 'VAT Input (Recoverable)', 'liability', '2130', 0, 'tax_input'];
            $tree[] = ['2133', 'VAT Reverse Charge', 'liability', '2130', 0, 'tax_rcm'];
            $tree[] = ['2150', 'Corporate Tax Payable', 'liability', '2100', 0, null];
        } elseif ($regime === 'IN_GST') {
            foreach ([['2131', 'Output CGST'], ['2132', 'Output SGST / UTGST'], ['2133', 'Output IGST'], ['2134', 'Input CGST'], ['2135', 'Input SGST / UTGST'], ['2136', 'Input IGST'], ['2137', 'GST Cess'], ['2138', 'TDS Payable'], ['2139', 'GST RCM Payable']] as [$c, $n]) {
                $tree[] = [$c, $n, 'liability', '2130', 0, str_starts_with($n, 'Input') ? 'tax_input' : 'tax_output'];
            }
        } else {
            $tree[] = ['2131', 'Sales Tax Payable', 'liability', '2130', 0, 'tax_output'];
        }

        $ids = [];
        foreach ($tree as [$code, $name, $type, $parent, $isGroup, $sub]) {
            $existing = Database::value('SELECT id FROM accounts WHERE company_id = ? AND code = ?', [$company['id'], $code]);
            $ids[$code] = $existing ? (int) $existing : Database::insert('accounts', [
                'company_id' => $company['id'], 'parent_id' => $parent ? ($ids[$parent] ?? null) : null,
                'code' => $code, 'name' => $name, 'account_type' => $type, 'sub_type' => $sub,
                'is_group' => $isGroup, 'is_system' => 1, 'currency_code' => $company['base_currency'],
            ]);
        }
        return $ids;
    }

    private static function taxes(array $company, array $acc): void
    {
        $rows = match ($company['tax_regime']) {
            'UAE_VAT' => [
                ['Standard Rated 5%', 'VAT5', 'VAT', 'standard', 5, 1, '2131', '2132'],
                ['Zero Rated 0%', 'VAT0', 'VAT', 'zero', 0, 0, '2131', '2132'],
                ['Exempt', 'EXEMPT', 'VAT', 'exempt', 0, 0, null, null],
                ['Reverse Charge 5%', 'RCM5', 'VAT', 'reverse_charge', 5, 0, '2133', '2132'],
                ['Out of Scope', 'OOS', 'VAT', 'out_of_scope', 0, 0, null, null],
            ],
            // India GST 2.0 (from 22-Sep-2025): 0 / 5 / 18 / 40 + special rates.
            // CGST+SGST (intra-state) or IGST (inter-state) is decided per document by place of supply.
            'IN_GST' => [
                ['GST 0% (Nil)', 'GST0', 'GST', 'zero', 0, 0, '2133', '2136'],
                ['GST 5%', 'GST5', 'GST', 'standard', 5, 0, '2133', '2136'],
                ['GST 18%', 'GST18', 'GST', 'standard', 18, 1, '2133', '2136'],
                ['GST 40%', 'GST40', 'GST', 'standard', 40, 0, '2133', '2136'],
                ['GST 3% (Gold/Silver)', 'GST3', 'GST', 'standard', 3, 0, '2133', '2136'],
                ['GST 0.25% (Rough diamonds)', 'GST025', 'GST', 'standard', 0.25, 0, '2133', '2136'],
                ['Exempt', 'EXEMPT', 'GST', 'exempt', 0, 0, null, null],
                ['GST RCM 18%', 'RCM18', 'GST', 'reverse_charge', 18, 0, '2139', '2136'],
            ],
            default => [['No Tax', 'NONE', 'OTHER', 'out_of_scope', 0, 1, null, null]],
        };
        foreach ($rows as [$name, $code, $type, $cat, $rate, $default, $out, $in]) {
            Database::query(
                'INSERT IGNORE INTO tax_rates (company_id, name, code, tax_type, category, rate, output_account_id, input_account_id, is_default) VALUES (?,?,?,?,?,?,?,?,?)',
                [$company['id'], $name, $code, $type, $cat, $rate, $out ? ($acc[$out] ?? null) : null, $in ? ($acc[$in] ?? null) : null, $default]
            );
        }
    }
}
