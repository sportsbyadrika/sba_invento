<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;

$cid = static fn () => Auth::instance()->companyId();
$kinds = ['bank' => ['Bank', 'cyan'], 'cash' => ['Cash', 'emerald'], 'card' => ['Credit Card', 'amber'], 'wallet' => ['Wallet / PG', 'violet']];
$balance = "(CASE WHEN a.opening_type='Dr' THEN a.opening_balance ELSE -a.opening_balance END
            + COALESCE((SELECT SUM(l.debit_base - l.credit_base) FROM journal_lines l JOIN journals j ON j.id = l.journal_id AND j.status='posted' WHERE l.account_id = t.account_id),0))";

return [
    'title' => 'Bank & Cash Accounts',
    'singular' => 'Bank Account',
    'icon' => 'landmark',
    'description' => 'Each bank / cash account is linked to its own ledger in the chart of accounts (created automatically).',
    'table' => 'bank_accounts',
    'from' => 'bank_accounts t JOIN accounts a ON a.id = t.account_id',
    'columns' => [
        'account_name' => ['label' => 'Account', 'sql' => 't.account_name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['account_name']) . '</span><span class="block text-xs text-slate-500">' . e($r['bank_name']) . '</span>'],
        'account_kind' => ['label' => 'Kind', 'sql' => 't.account_kind', 'format' => 'badge', 'map' => $kinds],
        'account_number' => ['label' => 'Account No.', 'sql' => 't.account_number', 'render' => static fn ($r) => '<span class="font-mono text-xs">•••• ' . e(substr((string) $r['account_number'], -4)) . '</span>'],
        'iban' => ['label' => 'IBAN', 'sql' => 't.iban', 'format' => 'code'],
        'swift_ifsc' => ['label' => 'SWIFT / IFSC', 'sql' => 't.swift_ifsc', 'format' => 'code'],
        'currency_code' => ['label' => 'Cur', 'sql' => 't.currency_code', 'format' => 'code'],
        'ledger' => ['label' => 'Ledger', 'sql' => "CONCAT(a.code, ' · ', a.name)"],
        'balance' => ['label' => 'Balance (Base)', 'sql' => $balance, 'format' => 'money', 'align' => 'right'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'select_extra' => ['bank_name' => 't.bank_name'],
    'default_sort' => ['account_name', 'asc'],
    'search' => ['t.account_name', 't.bank_name', 't.account_number', 't.iban'],
    'filters' => [
        'account_kind' => ['label' => 'Kind', 'type' => 'select', 'sql' => 't.account_kind', 'options' => array_map(static fn ($k) => $k[0], $kinds)],
        'currency_code' => ['label' => 'Currency', 'type' => 'select', 'sql' => 't.currency_code', 'options' => static fn () => array_column(Database::all('SELECT code FROM currencies WHERE company_id = ? ORDER BY code', [$cid()]), 'code', 'code')],
        'is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']],
    ],
    'stats' => [
        ['label' => 'Accounts', 'sql' => 'COUNT(*)', 'icon' => 'landmark'],
        ['label' => 'Total Balance', 'sql' => "COALESCE(SUM($balance),0)", 'format' => 'money', 'icon' => 'wallet', 'tone' => 'emerald'],
        ['label' => 'Foreign Currency', 'sql' => 'COALESCE(SUM(t.currency_code <> a.currency_code),0)', 'icon' => 'globe', 'tone' => 'indigo'],
    ],
    'fields' => [
        'account_kind' => ['label' => 'Account Kind', 'type' => 'select', 'required' => true, 'options' => array_map(static fn ($k) => $k[0], $kinds), 'default' => 'bank'],
        'currency_code' => ['label' => 'Currency', 'type' => 'lookup', 'required' => true, 'default' => Auth::instance()->company()['base_currency'] ?? null, 'options' => static fn () => array_column(Database::all('SELECT code, CONCAT(code, " - ", name) label FROM currencies WHERE company_id = ? AND is_active = 1 ORDER BY is_base DESC, code', [$cid()]), 'label', 'code')],
        'account_name' => ['label' => 'Account Name', 'required' => true, 'max' => 100, 'placeholder' => 'e.g. ENBD Current A/c', 'col' => 2],
        'bank_name' => ['label' => 'Bank Name', 'required' => true, 'max' => 100],
        'branch' => ['label' => 'Branch', 'max' => 100],
        'account_number' => ['label' => 'Account Number', 'required' => true, 'max' => 40],
        'iban' => ['label' => 'IBAN', 'max' => 40, 'uppercase' => true, 'pattern' => '/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', 'pattern_message' => 'Enter a valid IBAN without spaces (e.g. AE070331234567890123456).'],
        'swift_ifsc' => ['label' => 'SWIFT / IFSC', 'max' => 20, 'uppercase' => true],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $existing): array {
            $cid = Auth::instance()->companyId();
            if ($d['account_kind'] !== 'bank' && empty($d['account_number'])) {
                $d['account_number'] = '-';
            }
            if ($existing) {
                Database::update('accounts', ['name' => $d['account_name'], 'currency_code' => $d['currency_code']], 'id = ? AND company_id = ?', [$existing['account_id'], $cid]);
                return $d;
            }
            $sub = $d['account_kind'] === 'cash' ? 'cash' : 'bank';
            $group = Database::one("SELECT * FROM accounts WHERE company_id = ? AND is_group = 1 AND sub_type = 'bank' ORDER BY code LIMIT 1", [$cid])
                ?? Database::one("SELECT * FROM accounts WHERE company_id = ? AND is_group = 1 AND account_type = 'asset' ORDER BY code LIMIT 1", [$cid]);
            if (!$group) {
                throw new ValidationException(['account_name' => 'Create an asset group for bank accounts in the chart of accounts first.']);
            }
            $type = $d['account_kind'] === 'card' ? 'liability' : 'asset';
            if ($type === 'liability') {
                $group = Database::one("SELECT * FROM accounts WHERE company_id = ? AND is_group = 1 AND account_type = 'liability' AND parent_id IS NOT NULL ORDER BY code LIMIT 1", [$cid]) ?? $group;
            }
            $prefix = $group['code'];
            $n = 1;
            do {
                $code = $prefix . '-' . str_pad((string) $n++, 2, '0', STR_PAD_LEFT);
            } while (Database::value('SELECT COUNT(*) FROM accounts WHERE company_id = ? AND code = ?', [$cid, $code]));
            $d['account_id'] = Database::insert('accounts', [
                'company_id' => $cid, 'parent_id' => $group['account_type'] === $type ? $group['id'] : null, 'code' => $code, 'name' => $d['account_name'],
                'account_type' => $type, 'sub_type' => $sub, 'is_group' => 0, 'currency_code' => $d['currency_code'], 'created_by' => Auth::instance()->id(),
            ]);
            return $d;
        },
        'before_delete' => static fn ($r) => Database::value('SELECT COUNT(*) FROM journal_lines WHERE account_id = ?', [$r['account_id']]) ? 'This bank account has transactions. Mark it inactive instead.' : null,
    ],
];
