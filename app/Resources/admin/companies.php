<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Services\CompanyProvisioner;
use App\Services\TaxRules;

$regimes = ['NONE' => ['No tax', 'slate'], 'UAE_VAT' => ['UAE VAT', 'cyan'], 'IN_GST' => ['India GST', 'amber']];
$countries = ['AE' => 'United Arab Emirates', 'IN' => 'India', 'SA' => 'Saudi Arabia', 'OM' => 'Oman', 'QA' => 'Qatar', 'BH' => 'Bahrain', 'KW' => 'Kuwait', 'GB' => 'United Kingdom', 'US' => 'United States'];
$months = array_combine(range(1, 12), array_map(static fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)));

return [
    'title' => 'Companies',
    'singular' => 'Company',
    'icon' => 'building-2',
    'super_only' => true,
    'description' => 'Tenant companies of the SaaS platform. Creating a company seeds its chart of accounts, taxes and currencies and creates its admin user.',
    'table' => 'companies',
    'tenant' => false,
    'from' => 'companies t',
    'creator' => true,
    'can_delete' => false,
    'drawer_width' => 'max-w-3xl',
    'columns' => [
        'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code'],
        'name' => ['label' => 'Company', 'sql' => 't.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['name']) . '</span><span class="block text-xs text-slate-500">' . e($r['email']) . '</span>'],
        'country_code' => ['label' => 'Country', 'sql' => 't.country_code', 'format' => 'code'],
        'tax_regime' => ['label' => 'Tax', 'sql' => 't.tax_regime', 'format' => 'badge', 'map' => $regimes],
        'base_currency' => ['label' => 'Base Cur', 'sql' => 't.base_currency', 'format' => 'code'],
        'plan' => ['label' => 'Plan', 'sql' => 't.plan', 'render' => static fn ($r) => e(ucfirst((string) $r['plan']))],
        'users' => ['label' => 'Users', 'sql' => '(SELECT COUNT(*) FROM user_companies uc WHERE uc.company_id = t.id)', 'render' => static fn ($r) => e($r['users']) . ' / ' . e($r['max_users']), 'align' => 'right'],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => ['active' => ['Active', 'green'], 'suspended' => ['Suspended', 'rose']]],
        'created_at' => ['label' => 'Created', 'sql' => 't.created_at', 'format' => 'datetime'],
    ],
    'select_extra' => ['email' => 't.email', 'max_users' => 't.max_users'],
    'default_sort' => ['name', 'asc'],
    'search' => ['t.code', 't.name', 't.email', 't.tax_number'],
    'filters' => [
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => ['active' => 'Active', 'suspended' => 'Suspended']],
        'tax_regime' => ['label' => 'Tax Regime', 'type' => 'select', 'sql' => 't.tax_regime', 'options' => array_map(static fn ($r) => $r[0], $regimes)],
        'country_code' => ['label' => 'Country', 'type' => 'select', 'sql' => 't.country_code', 'options' => $countries],
        'plan' => ['label' => 'Plan', 'type' => 'select', 'sql' => 't.plan', 'options' => ['starter' => 'Starter', 'growth' => 'Growth', 'enterprise' => 'Enterprise']],
        'created' => ['label' => 'Created', 'type' => 'daterange', 'sql' => 't.created_at'],
    ],
    'stats' => [
        ['label' => 'Companies', 'sql' => 'COUNT(*)', 'icon' => 'building-2'],
        ['label' => 'Active', 'sql' => "COALESCE(SUM(t.status='active'),0)", 'icon' => 'circle-check', 'tone' => 'emerald'],
        ['label' => 'UAE VAT', 'sql' => "COALESCE(SUM(t.tax_regime='UAE_VAT'),0)", 'icon' => 'percent', 'tone' => 'cyan'],
        ['label' => 'India GST', 'sql' => "COALESCE(SUM(t.tax_regime='IN_GST'),0)", 'icon' => 'percent', 'tone' => 'amber'],
    ],
    'fields' => [
        'code' => ['label' => 'Company Code', 'required' => true, 'max' => 20, 'unique' => true, 'uppercase' => true, 'pattern' => '/^[A-Za-z0-9\-]{2,20}$/', 'pattern_message' => '2-20 letters, digits or dashes.'],
        'name' => ['label' => 'Company Name', 'required' => true, 'max' => 150],
        'legal_name' => ['label' => 'Legal Name', 'max' => 200, 'col' => 2],
        'country_code' => ['label' => 'Country', 'type' => 'select', 'required' => true, 'options' => $countries, 'default' => 'AE'],
        'tax_regime' => ['label' => 'Tax Regime', 'type' => 'select', 'required' => true, 'options' => array_map(static fn ($r) => $r[0], $regimes), 'default' => 'UAE_VAT', 'create_only' => true, 'help' => 'Decides the default taxes and tax accounts.'],
        'tax_number' => ['label' => 'TRN / GSTIN', 'max' => 30, 'uppercase' => true],
        'state_code' => ['label' => 'Emirate / State Code', 'max' => 10, 'help' => 'e.g. DXB or 32 (Kerala)'],
        'base_currency' => ['label' => 'Base Currency', 'type' => 'select', 'required' => true, 'create_only' => true, 'options' => array_combine(array_keys(CompanyProvisioner::CURRENCIES), array_map(static fn ($c, $k) => "$k - {$c[0]}", CompanyProvisioner::CURRENCIES, array_keys(CompanyProvisioner::CURRENCIES))), 'default' => 'AED', 'help' => 'Cannot be changed after creation.'],
        'fiscal_year_start' => ['label' => 'Financial Year Starts', 'type' => 'select', 'options' => $months, 'default' => '1'],
        'timezone' => ['label' => 'Time Zone', 'type' => 'select', 'options' => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()), 'default' => 'Asia/Dubai'],
        'email' => ['label' => 'Company E-mail', 'type' => 'email', 'max' => 150],
        'phone' => ['label' => 'Phone', 'max' => 40],
        'plan' => ['label' => 'Plan', 'type' => 'select', 'options' => ['starter' => 'Starter', 'growth' => 'Growth', 'enterprise' => 'Enterprise'], 'default' => 'starter'],
        'max_users' => ['label' => 'Max Users', 'type' => 'number', 'min' => 1, 'default' => 5],
        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'suspended' => 'Suspended'], 'default' => 'active'],
        'section_admin' => ['label' => 'Company administrator', 'type' => 'section', 'create_only' => true],
        'existing_admin' => ['label' => 'Use an existing user (optional)', 'type' => 'lookup', 'virtual' => true, 'create_only' => true, 'col' => 2,
            'options' => static fn () => array_column(Database::all("SELECT id, CONCAT(username, ' - ', name) label FROM users WHERE status <> 'disabled' ORDER BY username"), 'label', 'id'),
            'help' => 'Pick an existing user to make them admin of this company too, or fill the fields below to create a new admin.'],
        'admin_username' => ['label' => 'Admin Username', 'lowercase' => true, 'virtual' => true, 'create_only' => true, 'max' => 50, 'pattern' => '/^[a-z0-9][a-z0-9._\-]{2,49}$/', 'pattern_message' => '3-50 lower-case letters, digits, dot, dash, underscore.'],
        'admin_name' => ['label' => 'Admin Full Name', 'virtual' => true, 'create_only' => true, 'max' => 120],
        'admin_email' => ['label' => 'Admin E-mail', 'type' => 'email', 'virtual' => true, 'create_only' => true, 'max' => 150],
        'admin_password' => ['label' => 'Admin Password', 'type' => 'password', 'virtual' => true, 'create_only' => true],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $e): array {
            $regime = $e['tax_regime'] ?? $d['tax_regime'];
            if ($err = TaxRules::validateTaxNumber($regime, (string) ($d['tax_number'] ?? ''), $regime === 'IN_GST' ? (string) ($d['state_code'] ?? '') : '')) {
                throw new ValidationException(['tax_number' => $err]);
            }
            if (!$e && empty($d['existing_admin'])) {
                $errors = [];
                foreach (['admin_username' => 'Admin username', 'admin_name' => 'Admin name', 'admin_email' => 'Admin e-mail', 'admin_password' => 'Admin password'] as $k => $l) {
                    if (empty($d[$k])) {
                        $errors[$k] = "$l is required (or pick an existing user).";
                    }
                }
                if (!empty($d['admin_username']) && Database::value('SELECT COUNT(*) FROM users WHERE username = ?', [strtolower($d['admin_username'])])) {
                    $errors['admin_username'] = 'This username is already taken.';
                }
                if (!empty($d['admin_password']) && ($err = Auth::passwordPolicyError($d['admin_password']))) {
                    $errors['admin_password'] = $err;
                }
                if ($errors) {
                    throw new ValidationException($errors);
                }
            }
            $d['tax_number'] = ($d['tax_number'] ?? '') ?: null;
            $d['state_code'] = ($d['state_code'] ?? '') ?: null;
            return $d;
        },
        'after_save' => static function (int $id, array $d, ?array $e): void {
            if ($e) {
                return;
            }
            CompanyProvisioner::provision($id);
            $uid = (int) ($d['existing_admin'] ?? 0);
            if (!$uid) {
                $uid = Database::insert('users', [
                    'username' => strtolower($d['admin_username']), 'name' => $d['admin_name'], 'email' => $d['admin_email'],
                    'password_hash' => Auth::hash($d['admin_password']), 'must_change_password' => 1,
                    'default_company_id' => $id, 'created_by' => Auth::instance()->id(),
                ]);
            }
            Database::query("INSERT INTO user_companies (user_id, company_id, role) VALUES (?, ?, 'admin') ON DUPLICATE KEY UPDATE role = 'admin', is_active = 1", [$uid, $id]);
        },
    ],
    'row_actions' => [
        'suspend' => ['label' => 'Suspend', 'icon' => 'pause', 'tone' => 'rose', 'confirm' => 'Suspend this company? Its users will not be able to use it.',
            'visible' => static fn ($r) => $r['status'] === 'active',
            'handler' => static function ($row) {
                Database::update('companies', ['status' => 'suspended'], 'id = ?', [$row['id']]);
                return 'Company suspended.';
            }],
        'activate' => ['label' => 'Activate', 'icon' => 'play', 'tone' => 'emerald',
            'visible' => static fn ($r) => $r['status'] === 'suspended',
            'handler' => static function ($row) {
                Database::update('companies', ['status' => 'active'], 'id = ?', [$row['id']]);
                return 'Company activated.';
            }],
    ],
];
