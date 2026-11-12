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
        self::demoCommerce();
        echo "Demo data ready. Sign in with admin / Admin@12345\n";
    }

    /** Online shop, purchases, stock batches, quotes, invoices, web orders, returns and reviews */
    private static function demoCommerce(): void
    {
        $admin = Database::one("SELECT * FROM users WHERE username = 'admin'");
        if (!$admin) {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_SESSION)) {
            $_SESSION = [];
        }
        $meta = [
            'ELC-LAP-14' => ['Lenovo', "Processor: Intel Core i5\nMemory: 16 GB\nScreen size: 14 inch\nColour: Grey\nWarranty: 1 year", 'Thin and light 14" laptop for office and study.', 3690, 10],
            'ELC-MON-24' => ['Dell', "Screen size: 24 inch\nPanel: IPS\nResolution: Full HD\nColour: Black\nWarranty: 3 years", 'Full HD IPS monitor with slim bezels.', 799, null],
            'ELC-UPS-1K' => ['APC', "Capacity: 1 kVA\nColour: Black\nWarranty: 2 years", 'Keeps your PC running during power cuts.', null, null],
            'OFF-A4-500' => ['Double A', "Paper size: A4\nWeight: 80 gsm\nColour: White", 'Smooth A4 copier paper, 500 sheets.', 25, null],
            'OFF-PEN-BL' => ['Reynolds', "Colour: Blue\nPack: 50 pens", 'Smooth-writing ball pens for daily use.', null, null],
            'HDW-DRL-18' => ['Bosch', "Voltage: 18 V\nColour: Green\nWarranty: 2 years", 'Cordless drill driver with two batteries.', 499, null],
            'HDW-CBL-CAT6' => ['D-Link', "Length: 305 m\nColour: Blue\nCategory: CAT6", 'Pure copper CAT6 network cable box.', null, null],
            'SRV-INST' => [null, "Service: On-site", 'Professional on-site installation by our technician.', null, null],
        ];
        $colours = [[8, 145, 178], [99, 102, 241], [16, 185, 129], [245, 158, 11], [244, 63, 94], [20, 184, 166], [139, 92, 246], [100, 116, 139]];
        foreach (Database::all("SELECT id, code FROM companies WHERE code IN ('HTL','HIN') ORDER BY id") as $co) {
            $cid = (int) $co['id'];
            if (Database::value('SELECT COUNT(*) FROM shop_settings WHERE company_id = ?', [$cid])) {
                continue;
            }
            $isUae = $co['code'] === 'HTL';
            Auth::instance()->actAs($admin, $cid);
            $f = $isUae ? 1 : 22;
            Database::insert('shop_settings', [
                'company_id' => $cid, 'enabled' => 1, 'slug' => $isUae ? 'hallsons' : 'hallsons-india', 'store_name' => $isUae ? 'Hallsons Store' : 'Hallsons India Store',
                'tagline' => $isUae ? 'Office tech & supplies, delivered across the UAE' : 'Office & home essentials, delivered across India',
                'contact_email' => $isUae ? 'shop@hallsons.example' : 'shop@hallsons-india.example', 'contact_phone' => $isUae ? '+971 4 000 0000' : '+91 484 000 0000',
                'delivery_fee' => $isUae ? 15 : 49, 'free_delivery_min' => $isUae ? 200 : 999, 'cod_enabled' => 1, 'cod_max' => $isUae ? 5000 : 50000, 'cod_fee' => 0, 'return_days' => 7,
                'shipping_policy' => "Orders are delivered within 2-4 working days.\nDelivery is free above the minimum order value.",
                'returns_policy' => "Unused items can be returned within 7 days of delivery in their original packing.\nRefunds are made to your bank account within 5 working days after we receive the item.",
                'terms' => 'Prices include tax. Orders are confirmed by phone or e-mail before dispatch.', 'privacy' => 'We use your details only to deliver your orders. We never sell your data.',
            ]);
            // ---- publish items with images & specifications
            $i = 0;
            foreach (Database::all('SELECT * FROM items WHERE company_id = ?', [$cid]) as $it) {
                $m = $meta[$it['sku']] ?? null;
                if (!$m) {
                    continue;
                }
                Database::update('items', ['publish_online' => 1, 'brand' => $m[0], 'attributes' => $m[1], 'short_description' => $m[2], 'long_description' => $m[2] . "\n\nGenuine product with manufacturer warranty. Sold and delivered by Hallsons.",
                    'mrp' => $m[3] ? $m[3] * $f : null, 'online_stock_cap' => $m[4], 'slug' => str_slug($it['name'])], 'id = ?', [$it['id']]);
                ItemImages::placeholder($cid, (int) $it['id'], $m[0] ?: $it['sku'], $colours[$i++ % count($colours)]);
            }
            // ---- stock: one approved & received purchase order per goods item
            $vendor = (int) Database::value("SELECT id FROM contacts WHERE company_id = ? AND contact_type = 'vendor' ORDER BY id LIMIT 1", [$cid]);
            $tax = (int) Database::value('SELECT id FROM tax_rates WHERE company_id = ? AND is_default = 1', [$cid]) ?: null;
            $goods = Database::all("SELECT * FROM items WHERE company_id = ? AND item_type = 'goods'", [$cid]);
            $lines = array_map(static fn ($g) => ['item_id' => $g['id'], 'qty' => in_array($g['sku'], ['ELC-LAP-14', 'HDW-DRL-18'], true) ? 25 : 60, 'unit_price' => $g['purchase_price'], 'tax_rate_id' => $tax], $goods);
            $po = DocumentService::save('po', ['doc_date' => date('Y-m-d', strtotime('-40 days')), 'contact_id' => $vendor, 'reference' => 'VQ-1021'], $lines);
            Database::update('documents', ['status' => 'open'], 'id = ?', [$po]);
            $recv = [];
            foreach (Database::all('SELECT id, qty FROM document_lines WHERE document_id = ?', [$po]) as $l) {
                $recv[$l['id']] = $l['qty'];
            }
            DocumentService::receivePo($po, $recv, 120 * $f, date('Y-m-d', strtotime('-38 days')), 'DN-5521');
            // a second, still open purchase order
            $po2 = DocumentService::save('po', ['doc_date' => date('Y-m-d', strtotime('-2 days')), 'due_date' => date('Y-m-d', strtotime('+5 days')), 'contact_id' => $vendor], [['item_id' => $goods[0]['id'], 'qty' => 10, 'unit_price' => $goods[0]['purchase_price'], 'tax_rate_id' => $tax]]);
            Database::update('documents', ['status' => 'open'], 'id = ?', [$po2]);

            if (!$isUae) {
                // The client's example: 50 @ 220 (+30 freight) sold at 290, 35 sold, then 50 @ 250 (+10 freight) sold at 350
                $cat = (int) Database::value("SELECT id FROM item_categories WHERE company_id = ? AND name = 'Office Supplies'", [$cid]);
                $unit = (int) Database::value("SELECT id FROM units WHERE company_id = ? AND code = 'NOS'", [$cid]);
                $acc = static fn ($code) => (int) Database::value('SELECT id FROM accounts WHERE company_id = ? AND code = ?', [$cid, $code]);
                $bottle = Database::insert('items', ['company_id' => $cid, 'sku' => 'HOM-BTL-1L', 'name' => 'Steel Water Bottle 1L', 'slug' => 'steel-water-bottle-1l', 'item_type' => 'goods', 'category_id' => $cat, 'unit_id' => $unit,
                    'hsn_sac' => '73239390', 'tax_rate_id' => $tax, 'sales_price' => 290, 'purchase_price' => 220, 'sales_account_id' => $acc('4100'), 'purchase_account_id' => $acc('5200'), 'inventory_account_id' => $acc('1140'),
                    'track_inventory' => 1, 'brand' => 'Milton', 'publish_online' => 1, 'attributes' => "Capacity: 1 litre\nMaterial: Stainless steel\nColour: Silver", 'short_description' => 'Leak-proof stainless steel bottle that keeps water cool for hours.']);
                ItemImages::placeholder($cid, $bottle, 'MILTON', [71, 85, 105]);
                $cust = (int) Database::value("SELECT id FROM contacts WHERE company_id = ? AND contact_type = 'customer' ORDER BY id LIMIT 1", [$cid]);
                $p1 = DocumentService::save('po', ['doc_date' => date('Y-m-d', strtotime('-30 days')), 'contact_id' => $vendor, 'reference' => 'Batch A'], [['item_id' => $bottle, 'qty' => 50, 'unit_price' => 220, 'tax_rate_id' => $tax]]);
                Database::update('documents', ['status' => 'open'], 'id = ?', [$p1]);
                DocumentService::receivePo($p1, [(int) Database::value('SELECT id FROM document_lines WHERE document_id = ?', [$p1]) => 50], 30, date('Y-m-d', strtotime('-29 days')), 'A-220', [$bottle => 290]);
                $inv = DocumentService::save('invoice', ['doc_date' => date('Y-m-d', strtotime('-20 days')), 'due_date' => date('Y-m-d', strtotime('-5 days')), 'contact_id' => $cust], [['item_id' => $bottle, 'qty' => 35, 'unit_price' => 290, 'tax_rate_id' => $tax]]);
                DocumentService::postInvoice($inv);
                $p2 = DocumentService::save('po', ['doc_date' => date('Y-m-d', strtotime('-3 days')), 'contact_id' => $vendor, 'reference' => 'Batch B'], [['item_id' => $bottle, 'qty' => 50, 'unit_price' => 250, 'tax_rate_id' => $tax]]);
                Database::update('documents', ['status' => 'open'], 'id = ?', [$p2]);
                DocumentService::receivePo($p2, [(int) Database::value('SELECT id FROM document_lines WHERE document_id = ?', [$p2]) => 50], 10, date('Y-m-d', strtotime('-2 days')), 'B-250', [$bottle => 350]);
                echo "  HIN: bottle batches 15 @ 220.60 + 50 @ 250.20, price now 350\n";
                continue;
            }

            // ---- quotes & invoices (UAE)
            $customers = array_column(Database::all("SELECT id FROM contacts WHERE company_id = ? AND contact_type = 'customer' AND is_active = 1 ORDER BY id LIMIT 4", [$cid]), 'id');
            $byS = static fn ($sku) => Database::one('SELECT * FROM items WHERE company_id = ? AND sku = ?', [$cid, $sku]);
            $lap = $byS('ELC-LAP-14');
            $mon = $byS('ELC-MON-24');
            $ins = $byS('SRV-INST');
            $q1 = DocumentService::save('quote', ['doc_date' => date('Y-m-d', strtotime('-6 days')), 'due_date' => date('Y-m-d', strtotime('+24 days')), 'contact_id' => $customers[0], 'terms' => 'Prices valid for 30 days. Delivery within 3 days of order.'],
                [['item_id' => $lap['id'], 'qty' => 5, 'unit_price' => $lap['sales_price'], 'discount_pct' => 5, 'tax_rate_id' => $tax], ['item_id' => $ins['id'], 'qty' => 5, 'unit_price' => $ins['sales_price'], 'tax_rate_id' => $tax]]);
            Database::update('documents', ['status' => 'sent'], 'id = ?', [$q1]);
            $q2 = DocumentService::save('quote', ['doc_date' => date('Y-m-d', strtotime('-3 days')), 'due_date' => date('Y-m-d', strtotime('+27 days')), 'contact_id' => $customers[1]],
                [['item_id' => $mon['id'], 'qty' => 10, 'unit_price' => $mon['sales_price'], 'tax_rate_id' => $tax]]);
            Database::update('documents', ['status' => 'accepted'], 'id = ?', [$q2]);
            $i1 = DocumentService::save('invoice', ['doc_date' => date('Y-m-d', strtotime('-35 days')), 'due_date' => date('Y-m-d', strtotime('-5 days')), 'contact_id' => $customers[2], 'reference' => 'LPO-778'],
                [['item_id' => $mon['id'], 'qty' => 4, 'unit_price' => $mon['sales_price'], 'tax_rate_id' => $tax]]);
            DocumentService::postInvoice($i1);
            $i2 = DocumentService::save('invoice', ['doc_date' => date('Y-m-d', strtotime('-12 days')), 'due_date' => date('Y-m-d', strtotime('+18 days')), 'contact_id' => $customers[3]],
                [['item_id' => $lap['id'], 'qty' => 2, 'unit_price' => $lap['sales_price'], 'tax_rate_id' => $tax]]);
            DocumentService::postInvoice($i2);
            $bank = (int) Database::value("SELECT id FROM accounts WHERE company_id = ? AND sub_type = 'bank' AND is_group = 0 ORDER BY code LIMIT 1", [$cid]);
            DocumentService::recordPayment($i2, (float) Database::value('SELECT total FROM documents WHERE id = ?', [$i2]), $bank, date('Y-m-d', strtotime('-4 days')), 'TT-55102');

            // ---- online shop customers & orders in every stage
            $store = Shop::store('hallsons');
            $people = [['Aisha Rahman', '501234567', 'aisha@example.com', 'Dubai', 'DXB'], ['Rohan Mehta', '552345678', 'rohan@example.com', 'Abu Dhabi', 'AUH'], ['Sara Ali', '563456789', 'sara@example.com', 'Sharjah', 'SHJ']];
            $custRows = [];
            foreach ($people as [$name, $phone, $email, $city, $em]) {
                $cId = Database::insert('shop_customers', ['company_id' => $cid, 'name' => $name, 'phone_code' => '+971', 'phone' => $phone, 'email' => $email, 'password_hash' => Auth::hash('Shop@12345')]);
                $aId = Database::insert('shop_addresses', ['customer_id' => $cId, 'label' => 'Home', 'name' => $name, 'phone' => "+971 $phone", 'line1' => 'Flat 1203, Marina Heights, Street 4', 'city' => $city, 'region' => $em, 'country' => 'AE', 'is_default' => 1]);
                $custRows[] = [Database::one('SELECT * FROM shop_customers WHERE id = ?', [$cId]), Database::one('SELECT * FROM shop_addresses WHERE id = ?', [$aId])];
            }
            $items = Shop::catalogue($cid);
            $pick = static fn ($sku) => (int) current(array_filter($items, static fn ($x) => $x['sku'] === $sku))['id'];
            $orders = [
                [0, [['ELC-MON-24', 1], ['OFF-A4-500', 4]], 'delivered', true],
                [1, [['HDW-DRL-18', 1]], 'delivered', false],
                [2, [['ELC-UPS-1K', 1], ['OFF-PEN-BL', 2]], 'shipped', false],
                [0, [['ELC-LAP-14', 1]], 'packed', false],
                [1, [['HDW-CBL-CAT6', 1]], 'confirmed', false],
                [2, [['OFF-A4-500', 10]], 'placed', false],
                [1, [['OFF-PEN-BL', 1]], 'cancelled', false],
            ];
            foreach ($orders as [$who, $lines, $stage, $withReturn]) {
                [$c, $a] = $custRows[$who];
                Shop::cartClear($cid);
                foreach ($lines as [$sku, $q]) {
                    Shop::cartSet($cid, $pick($sku), $q);
                }
                $oid = Shop::placeOrder($store, $c, $a, 'cod', null);
                if ($stage === 'cancelled') {
                    Shop::advance($oid, 'cancelled', ['note' => 'Customer asked to cancel']);
                } elseif ($stage !== 'placed') {
                    Shop::advance($oid, $stage, $stage === 'shipped' || $stage === 'delivered' ? ['courier' => 'Aramex', 'tracking_no' => 'AWB' . random_int(100000, 999999)] : []);
                }
                if ($stage === 'delivered') {
                    $o = Shop::order($cid, $oid);
                    $line = $o['lines'][0];
                    Database::insert('shop_reviews', ['company_id' => $cid, 'item_id' => $line['item_id'], 'customer_id' => $c['id'], 'order_id' => $oid, 'rating' => $withReturn ? 4 : 5,
                        'title' => $withReturn ? 'Good value' : 'Excellent quality', 'body' => $withReturn ? 'Works well, delivery was quick.' : 'Exactly as described. Fast delivery and the cash on delivery option is convenient.', 'status' => 'approved', 'created_at' => now_utc()]);
                    if ($withReturn) {
                        Shop::requestReturn($store, $c, $o, [$o['lines'][1]['id'] => 1], 'damaged', 'One pack of paper was wet when it arrived.');
                    }
                }
            }
            Shop::cartClear($cid);
            echo "  HTL: shop 'hallsons' with 7 web orders, 2 quotes, 2 invoices\n";
        }
    }
}
