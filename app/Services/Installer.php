<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;

/**
 * Creates the database tables, the platform super admin and (optionally) demo data.
 * Used by both the CLI installer (bin/install.php) and the web installer (/install).
 */
final class Installer
{
    public static function dropAll(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $pdo->exec("DROP TABLE `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Runs database/schema.sql (all statements are CREATE TABLE IF NOT EXISTS - safe to repeat). */
    public static function schema(): void
    {
        $pdo = Database::pdo();
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $pdo->exec($stmt);
        }
        Migrator::run(); // later changes (database/migrations/*.sql)
    }

    public static function hasUsers(): bool
    {
        try {
            return (int) Database::value('SELECT COUNT(*) FROM users') > 0;
        } catch (\Throwable) {
            return false; // table does not exist yet
        }
    }

    /** @return int user id (existing or new) */
    public static function superAdmin(string $username, string $email, string $password, string $name = 'Platform Super Admin'): int
    {
        $username = strtolower(trim($username));
        if ($id = Database::value('SELECT id FROM users WHERE username = ?', [$username])) {
            return (int) $id;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._\-]{2,49}$/', $username)) {
            throw new \InvalidArgumentException('Username: 3-50 lower-case letters, digits, dot, dash or underscore.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid e-mail address.');
        }
        if ($err = Auth::passwordPolicyError($password)) {
            throw new \InvalidArgumentException($err);
        }
        return Database::insert('users', [
            'username' => $username, 'name' => $name, 'email' => $email,
            'password_hash' => Auth::hash($password), 'is_super_admin' => 1, 'password_changed_at' => now_utc(),
        ]);
    }

    /** Two demo companies (UAE + India) with users, masters and journals. Echoes progress lines. */
    public static function demo(int $superId): void
    {
        $companies = [
            ['code' => 'HTL', 'name' => 'Hallsons Trading LLC', 'legal_name' => 'Hallsons General Trading L.L.C', 'country_code' => 'AE', 'state_code' => 'DXB',
             'tax_regime' => 'UAE_VAT', 'tax_number' => '100123456700003', 'base_currency' => 'AED', 'fiscal_year_start' => 1, 'timezone' => 'Asia/Dubai',
             'email' => 'accounts@hallsons.example', 'phone' => '+971 4 000 0000', 'address' => "Office 1204, Business Bay\nDubai, UAE", 'plan' => 'growth', 'max_users' => 15],
            ['code' => 'HIN', 'name' => 'Hallsons India Pvt Ltd', 'legal_name' => 'Hallsons India Private Limited', 'country_code' => 'IN', 'state_code' => '32',
             'tax_regime' => 'IN_GST', 'tax_number' => '32AAPFU0939F1ZV', 'base_currency' => 'INR', 'fiscal_year_start' => 4, 'timezone' => 'Asia/Kolkata',
             'email' => 'accounts@hallsons-india.example', 'phone' => '+91 484 000 0000', 'address' => "MG Road\nKochi, Kerala 682016", 'plan' => 'starter', 'max_users' => 5],
        ];
        $ids = [];
        foreach ($companies as $c) {
            $id = (int) Database::value('SELECT id FROM companies WHERE code = ?', [$c['code']]);
            if (!$id) {
                $id = Database::insert('companies', $c + ['created_by' => $superId]);
                CompanyProvisioner::provision($id);
            }
            $ids[$c['code']] = $id;
        }

        $users = [
            ['admin', 'Manoj Pillai', 'admin@hallsons.example', 'Admin@12345', ['HTL' => 'admin', 'HIN' => 'admin']],
            ['accountant', 'Fatima Al Zahra', 'accountant@hallsons.example', 'Account@12345', ['HTL' => 'accountant', 'HIN' => 'viewer']],
            ['sales', 'Rahul Menon', 'sales@hallsons.example', 'Sales@12345', ['HTL' => 'sales']],
        ];
        foreach ($users as [$u, $n, $em, $pw, $links]) {
            $uid = (int) Database::value('SELECT id FROM users WHERE username = ?', [$u]);
            if (!$uid) {
                $uid = Database::insert('users', ['username' => $u, 'name' => $n, 'email' => $em, 'password_hash' => Auth::hash($pw), 'default_company_id' => $ids['HTL'], 'created_by' => $superId, 'password_changed_at' => now_utc()]);
            }
            foreach ($links as $code => $role) {
                Database::query('INSERT IGNORE INTO user_companies (user_id, company_id, role) VALUES (?,?,?)', [$uid, $ids[$code], $role]);
            }
            echo "  user $u / $pw\n";
        }

        $acc = static fn (int $cid, string $code) => (int) Database::value('SELECT id FROM accounts WHERE company_id = ? AND code = ?', [$cid, $code]);

        foreach ($ids as $code => $cid) {
            if (Database::value('SELECT COUNT(*) FROM contacts WHERE company_id = ?', [$cid])) {
                continue;
            }
            $isUae = $code === 'HTL';
            // opening balances (balanced: capital = cash + bank + stock)
            Database::update('accounts', ['opening_balance' => $isUae ? 25000 : 150000, 'opening_type' => 'Dr'], 'company_id = ? AND code = ?', [$cid, '1110']);
            Database::update('accounts', ['opening_balance' => $isUae ? 80000 : 600000, 'opening_type' => 'Dr'], 'company_id = ? AND code = ?', [$cid, '1140']);
            Database::update('accounts', ['opening_balance' => $isUae ? 105000 : 750000, 'opening_type' => 'Cr'], 'company_id = ? AND code = ?', [$cid, '3100']);

            // bank account with its own ledger
            $bankGroup = $acc($cid, '1120');
            $bankLedger = Database::insert('accounts', ['company_id' => $cid, 'parent_id' => $bankGroup, 'code' => '1120-01', 'name' => $isUae ? 'Emirates NBD Current A/c' : 'HDFC Bank Current A/c', 'account_type' => 'asset', 'sub_type' => 'bank', 'currency_code' => $isUae ? 'AED' : 'INR']);
            Database::insert('bank_accounts', ['company_id' => $cid, 'account_id' => $bankLedger, 'bank_name' => $isUae ? 'Emirates NBD' : 'HDFC Bank', 'account_name' => $isUae ? 'Emirates NBD Current A/c' : 'HDFC Bank Current A/c', 'account_number' => $isUae ? '1015123456701' : '50200012345678', 'iban' => $isUae ? 'AE070331234567890123456' : null, 'swift_ifsc' => $isUae ? 'EBILAEAD' : 'HDFC0000519', 'currency_code' => $isUae ? 'AED' : 'INR']);
            if ($isUae) {
                $usdLedger = Database::insert('accounts', ['company_id' => $cid, 'parent_id' => $bankGroup, 'code' => '1120-02', 'name' => 'Mashreq USD A/c', 'account_type' => 'asset', 'sub_type' => 'bank', 'currency_code' => 'USD']);
                Database::insert('bank_accounts', ['company_id' => $cid, 'account_id' => $usdLedger, 'bank_name' => 'Mashreq Bank', 'account_name' => 'Mashreq USD A/c', 'account_number' => '019100012345', 'iban' => 'AE460330000019100012345', 'swift_ifsc' => 'BOMLAEAD', 'currency_code' => 'USD']);
            }

            $places = $isUae ? ['DXB', 'AUH', 'SHJ', 'AJM', 'RAK'] : ['32', '33', '29', '27', '07'];
            $custNames = $isUae
                ? ['Al Noor Supermarket LLC', 'Gulf Star Contracting', 'Blue Wave Hospitality', 'Desert Rose Trading', 'Emirates Build Mart', 'Oasis Facility Mgmt', 'Palm Retail Group', 'Zenith Marine Services', 'Crescent Pharmacy', 'Sky Tower Holdings', 'Golden Sands Hotel', 'Marina Electricals']
                : ['Kerala Agro Traders', 'Chennai Steel Works', 'Bangalore Tech Retail', 'Mumbai Textiles Co', 'Delhi Hardware Mart', 'Kochi Spices Exports', 'Malabar Hospitals', 'Trivandrum Builders', 'Mysore Silk House', 'Coimbatore Pumps'];
            foreach ($custNames as $i => $nm) {
                Database::insert('contacts', ['company_id' => $cid, 'contact_type' => 'customer', 'code' => NumberSeries::next($cid, 'customer'), 'name' => $nm,
                    'contact_person' => ['Ahmed', 'Priya', 'John', 'Sara', 'Vikram', 'Leila'][$i % 6] . ' ' . ['K', 'M', 'S', 'R'][$i % 4],
                    'email' => 'accounts' . ($i + 1) . '@' . str_slug($nm) . '.example', 'phone' => $isUae ? '+971 50 ' . (1000000 + $i * 7919) : '+91 98' . (40000000 + $i * 7919),
                    'tax_treatment' => $i % 3 === 2 ? ($isUae ? 'non_registered' : 'unregistered') : ($isUae ? 'vat_registered' : 'registered'),
                    'tax_number' => $i % 3 === 2 ? null : ($isUae ? '100' . str_pad((string) (3456789012 + $i * 37), 12, '0', STR_PAD_LEFT) : null),
                    'place_of_supply' => $places[$i % 5], 'currency_code' => $isUae && $i % 4 === 3 ? 'USD' : ($isUae ? 'AED' : 'INR'),
                    'payment_terms' => [0, 15, 30, 45][$i % 4], 'credit_limit' => ($i + 1) * ($isUae ? 5000 : 50000), 'opening_balance' => $i % 2 ? ($i * ($isUae ? 1250 : 12500)) : 0,
                    'billing_address' => ($isUae ? ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah'] : ['Kochi', 'Chennai', 'Bengaluru', 'Mumbai', 'New Delhi'])[$i % 5], 'is_active' => $i === 11 ? 0 : 1]);
            }
            $vendNames = $isUae ? ['Jebel Ali Distributors', 'Al Futtaim Supplies', 'Sharjah Packaging Ind.', 'Global Freight FZE', 'Prime Office Solutions', 'Etisalat (e&)'] : ['Tata Steel Distributors', 'Reliance Packaging', 'Kochi Logistics', 'Infopark Office Supplies', 'KSEB (Electricity)'];
            foreach ($vendNames as $i => $nm) {
                Database::insert('contacts', ['company_id' => $cid, 'contact_type' => 'vendor', 'code' => NumberSeries::next($cid, 'vendor'), 'name' => $nm,
                    'email' => 'billing@' . str_slug($nm) . '.example', 'phone' => $isUae ? '+971 4 ' . (3000000 + $i * 911) : '+91 484 ' . (2000000 + $i * 911),
                    'tax_treatment' => $isUae ? 'vat_registered' : 'registered', 'place_of_supply' => $places[$i % 5], 'currency_code' => $isUae ? 'AED' : 'INR', 'payment_terms' => 30]);
            }
            // items
            $cats = [];
            foreach (['Electronics', 'Office Supplies', 'Hardware', 'Services'] as $cn) {
                $cats[$cn] = Database::insert('item_categories', ['company_id' => $cid, 'name' => $cn]);
            }
            $unit = static fn (string $c) => (int) Database::value('SELECT id FROM units WHERE company_id = ? AND code = ?', [$cid, $c]);
            $tax = (int) Database::value('SELECT id FROM tax_rates WHERE company_id = ? AND is_default = 1', [$cid]);
            $items = [
                ['ELC-LAP-14', 'Laptop 14" i5 16GB', 'goods', 'Electronics', 'NOS', '84713010', 3450, 2890],
                ['ELC-MON-24', 'Monitor 24" IPS', 'goods', 'Electronics', 'NOS', '85285200', 690, 520],
                ['ELC-UPS-1K', 'UPS 1 kVA', 'goods', 'Electronics', 'NOS', '85044010', 520, 470],
                ['OFF-A4-500', 'A4 Copier Paper (500 sheets)', 'goods', 'Office Supplies', 'BOX', '48025690', 22, 15.5],
                ['OFF-PEN-BL', 'Ball Pen Blue (box of 50)', 'goods', 'Office Supplies', 'BOX', '96081019', 35, 21],
                ['HDW-DRL-18', 'Cordless Drill 18V', 'goods', 'Hardware', 'PCS', '84672100', 410, 455],
                ['HDW-CBL-CAT6', 'CAT6 Cable 305m', 'goods', 'Hardware', 'BOX', '85444299', 380, 300],
                ['SRV-INST', 'Installation Service', 'service', 'Services', 'HRS', '998719', 150, 0],
                ['SRV-AMC', 'Annual Maintenance Contract', 'service', 'Services', 'NOS', '998713', 2400, 0],
            ];
            foreach ($items as [$sku, $nm, $type, $cat, $u, $hsn, $sp, $pp]) {
                $f = $isUae ? 1 : 22;
                Database::insert('items', ['company_id' => $cid, 'sku' => $sku, 'name' => $nm, 'item_type' => $type, 'category_id' => $cats[$cat], 'unit_id' => $unit($u), 'hsn_sac' => $hsn,
                    'tax_rate_id' => $tax ?: null, 'sales_price' => $sp * $f, 'purchase_price' => $pp * $f, 'sales_account_id' => $acc($cid, $type === 'service' ? '4200' : '4100'),
                    'purchase_account_id' => $type === 'service' ? null : $acc($cid, '5200'), 'inventory_account_id' => $type === 'service' ? null : $acc($cid, '1140'),
                    'track_inventory' => $type === 'goods' ? 1 : 0, 'reorder_level' => $type === 'goods' ? 10 : 0, 'barcode' => $type === 'goods' ? '629' . str_pad((string) crc32($sku), 10, '0', STR_PAD_LEFT) : null]);
            }

            // posted + draft journals over the last months
            $f = $isUae ? 1 : 20;
            $tx = [
                [-150, 'Capital introduced into bank', [['1120-01', 50000, 0], ['3100', 0, 50000]]],
                [-120, 'Office rent for the quarter', [['5320', 15000, 0], ['1120-01', 0, 15000]]],
                [-95, 'Cash sales deposited', [['1110', 18500, 0], ['4100', 0, 18500]]],
                [-80, 'Credit sales - month end summary', [['1130', 42000, 0], ['4100', 0, 42000]]],
                [-62, 'Salaries for the month', [['5310', 22000, 0], ['1120-01', 0, 22000]]],
                [-45, 'Collection from customers', [['1120-01', 30000, 0], ['1130', 0, 30000]]],
                [-40, 'Purchase of stock on credit', [['5200', 26000, 0], ['2110', 0, 26000]]],
                [-30, 'Service income received', [['1120-01', 12500, 0], ['4200', 0, 12500]]],
                [-21, 'Electricity and internet', [['5330', 2350, 0], ['1110', 0, 2350]]],
                [-14, 'Credit sales - fortnight', [['1130', 36500, 0], ['4100', 0, 36500]]],
                [-9, 'Payment to vendors', [['2110', 20000, 0], ['1120-01', 0, 20000]]],
                [-5, 'Bank charges', [['5350', 145, 0], ['1120-01', 0, 145]]],
                [-2, 'Office supplies (draft - pending bill)', [['5340', 860, 0], ['1110', 0, 860]], 'draft'],
            ];
            foreach ($tx as $row) {
                [$days, $narr, $lines] = $row;
                $status = $row[3] ?? 'posted';
                $total = array_sum(array_column($lines, 1)) * $f;
                $jid = Database::insert('journals', ['company_id' => $cid, 'number' => NumberSeries::next($cid, 'journal'), 'journal_date' => date('Y-m-d', strtotime("$days days")),
                    'reference' => 'REF-' . abs($days), 'narration' => $narr, 'currency_code' => $isUae ? 'AED' : 'INR', 'exchange_rate' => 1, 'total_amount' => $total, 'total_base' => $total,
                    'status' => $status, 'created_by' => (int) Database::value("SELECT id FROM users WHERE username = 'accountant'"), 'posted_at' => $status === 'posted' ? now_utc() : null]);
                foreach ($lines as $n => [$code, $dr, $cr]) {
                    Database::insert('journal_lines', ['journal_id' => $jid, 'company_id' => $cid, 'line_no' => $n + 1, 'account_id' => $acc($cid, $code),
                        'debit' => $dr * $f, 'credit' => $cr * $f, 'debit_base' => $dr * $f, 'credit_base' => $cr * $f]);
                }
            }
            if ($isUae) { // one foreign-currency journal
                $rate = (float) Database::value("SELECT exchange_rate FROM currencies WHERE company_id = ? AND code = 'USD'", [$cid]);
                $jid = Database::insert('journals', ['company_id' => $cid, 'number' => NumberSeries::next($cid, 'journal'), 'journal_date' => date('Y-m-d', strtotime('-7 days')), 'reference' => 'TT-88231',
                    'narration' => 'Export receipt in USD', 'currency_code' => 'USD', 'exchange_rate' => $rate, 'total_amount' => 5000, 'total_base' => round(5000 * $rate, 2), 'status' => 'posted', 'posted_at' => now_utc()]);
                Database::insert('journal_lines', ['journal_id' => $jid, 'company_id' => $cid, 'line_no' => 1, 'account_id' => $acc($cid, '1120-02'), 'debit' => 5000, 'credit' => 0, 'debit_base' => round(5000 * $rate, 2), 'credit_base' => 0]);
                Database::insert('journal_lines', ['journal_id' => $jid, 'company_id' => $cid, 'line_no' => 2, 'account_id' => $acc($cid, '4100'), 'debit' => 0, 'credit' => 5000, 'debit_base' => 0, 'credit_base' => round(5000 * $rate, 2)]);
            }
        }
        echo "Demo data ready. Sign in with admin / Admin@12345\n";
    }
}
