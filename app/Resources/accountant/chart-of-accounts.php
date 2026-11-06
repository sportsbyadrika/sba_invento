<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;

$types = [
    'asset' => ['Asset', 'cyan'], 'liability' => ['Liability', 'amber'], 'equity' => ['Equity', 'emerald'],
    'income' => ['Income', 'indigo'], 'expense' => ['Expense', 'rose'],
];
$cid = static fn () => Auth::instance()->companyId();
$groups = static fn () => array_column(Database::all(
    "SELECT id, CONCAT(code, ' · ', name) AS label FROM accounts WHERE company_id = ? AND is_group = 1 ORDER BY code",
    [$cid()]
), 'label', 'id');

return [
    'title' => 'Chart of Accounts',
    'singular' => 'Account',
    'icon' => 'git-fork',
    'description' => 'Groups and ledgers of your general ledger. Every transaction in every module posts to these accounts.',
    'table' => 'accounts',
    'from' => 'accounts t LEFT JOIN accounts p ON p.id = t.parent_id',
    'views' => ['tree' => '/accountant/chart-of-accounts/tree'],
    'creator' => true,
    'columns' => [
        'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code', 'width' => 'w-24'],
        'name' => ['label' => 'Account Name', 'sql' => 't.name', 'render' => static fn ($r) => ($r['is_group'] ? '<span class="font-semibold text-slate-900">' : '<span>') . e($r['name']) . '</span>' . ($r['is_system'] ? ' <span class="badge badge-slate ml-1">System</span>' : '')],
        'parent' => ['label' => 'Parent Group', 'sql' => 'p.name'],
        'account_type' => ['label' => 'Type', 'sql' => 't.account_type', 'format' => 'badge', 'map' => $types],
        'is_group' => ['label' => 'Kind', 'sql' => 't.is_group', 'format' => 'badge', 'map' => ['1' => ['Group', 'violet'], '0' => ['Ledger', 'slate']]],
        'currency_code' => ['label' => 'Currency', 'sql' => 't.currency_code', 'format' => 'code'],
        'opening' => ['label' => 'Opening', 'sql' => "(CASE WHEN t.opening_type='Dr' THEN t.opening_balance ELSE -t.opening_balance END)", 'format' => 'money', 'align' => 'right'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'select_extra' => ['is_system' => 't.is_system'],
    'default_sort' => ['code', 'asc'],
    'search' => ['t.code', 't.name', 'p.name'],
    'filters' => [
        'account_type' => ['label' => 'Account Type', 'type' => 'select', 'sql' => 't.account_type', 'multiple' => true, 'options' => array_map(static fn ($t) => $t[0], $types)],
        'is_group' => ['label' => 'Kind', 'type' => 'select', 'sql' => 't.is_group', 'options' => ['1' => 'Groups', '0' => 'Ledgers']],
        'parent_id' => ['label' => 'Under Group', 'type' => 'select', 'sql' => 't.parent_id', 'options' => $groups],
        'is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']],
        'opening' => ['label' => 'Opening Balance', 'type' => 'numrange', 'sql' => 't.opening_balance'],
        'created' => ['label' => 'Created On', 'type' => 'daterange', 'sql' => 't.created_at'],
    ],
    'stats' => [
        ['label' => 'Total Accounts', 'sql' => 'COUNT(*)', 'icon' => 'list', 'tone' => 'slate'],
        ['label' => 'Ledgers', 'sql' => 'COALESCE(SUM(t.is_group = 0),0)', 'icon' => 'book-open', 'tone' => 'cyan'],
        ['label' => 'Groups', 'sql' => 'COALESCE(SUM(t.is_group = 1),0)', 'icon' => 'folder-tree', 'tone' => 'violet'],
        ['label' => 'Inactive', 'sql' => 'COALESCE(SUM(t.is_active = 0),0)', 'icon' => 'circle-slash', 'tone' => 'rose'],
    ],
    'fields' => [
        'parent_id' => ['label' => 'Under Group', 'type' => 'lookup', 'options' => $groups, 'help' => 'Leave empty only for a primary group (Assets, Liabilities ...).', 'col' => 2, 'from_query' => true],
        'code' => ['label' => 'Account Code', 'required' => true, 'max' => 20, 'unique' => true, 'pattern' => '/^[A-Za-z0-9.\-]+$/', 'pattern_message' => 'Use letters, digits, dot or dash only.', 'placeholder' => 'e.g. 1135'],
        'name' => ['label' => 'Account Name', 'required' => true, 'max' => 150, 'placeholder' => 'e.g. Emirates NBD - Current A/c'],
        'account_type' => ['label' => 'Account Type', 'type' => 'select', 'options' => array_map(static fn ($t) => $t[0], $types), 'help' => 'Inherited from the parent group when one is selected.'],
        'is_group' => ['label' => 'This is a group (can hold sub-accounts)', 'type' => 'checkbox', 'create_only' => true],
        'currency_code' => ['label' => 'Currency', 'type' => 'lookup', 'options' => static fn () => array_column(Database::all('SELECT code, CONCAT(code, " - ", name) label FROM currencies WHERE company_id = ? AND is_active = 1 ORDER BY is_base DESC, code', [Auth::instance()->companyId()]), 'label', 'code')],
        'sub_type' => ['label' => 'Behaviour', 'type' => 'select', 'options' => ['' => 'General', 'cash' => 'Cash', 'bank' => 'Bank', 'receivable' => 'Accounts Receivable', 'payable' => 'Accounts Payable', 'tax_output' => 'Output Tax', 'tax_input' => 'Input Tax', 'stock' => 'Stock / Inventory', 'cogs' => 'Cost of Goods Sold', 'sales' => 'Sales', 'purchase' => 'Purchases', 'fixed' => 'Fixed Asset', 'fx' => 'Exchange Gain/Loss']],
        'section_ob' => ['label' => 'Opening balance', 'type' => 'section'],
        'opening_balance' => ['label' => 'Opening Balance (base currency)', 'type' => 'decimal', 'min' => 0, 'placeholder' => '0.00'],
        'opening_type' => ['label' => 'Dr / Cr', 'type' => 'select', 'options' => ['Dr' => 'Debit (Dr)', 'Cr' => 'Credit (Cr)'], 'default' => 'Dr'],
        'description' => ['label' => 'Description', 'type' => 'textarea', 'max' => 255, 'col' => 2],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $existing): array {
            $cid = Auth::instance()->companyId();
            if (!empty($d['parent_id'])) {
                $parent = Database::one('SELECT * FROM accounts WHERE id = ? AND company_id = ?', [$d['parent_id'], $cid]);
                if ($existing && (int) $d['parent_id'] === (int) $existing['id']) {
                    throw new ValidationException(['parent_id' => 'An account cannot be its own parent.']);
                }
                // prevent cycles: walk up from the new parent
                $p = $parent;
                while ($existing && $p && $p['parent_id']) {
                    if ((int) $p['parent_id'] === (int) $existing['id']) {
                        throw new ValidationException(['parent_id' => 'The selected group is inside this account.']);
                    }
                    $p = Database::one('SELECT id, parent_id FROM accounts WHERE id = ?', [$p['parent_id']]);
                }
                $d['account_type'] = $parent['account_type'];
            } elseif (empty($d['account_type'])) {
                throw new ValidationException(['account_type' => 'Select the account type for a primary group.']);
            }
            $isGroup = $existing ? (int) $existing['is_group'] : (int) ($d['is_group'] ?? 0);
            if ($isGroup && (float) ($d['opening_balance'] ?? 0) != 0.0) {
                throw new ValidationException(['opening_balance' => 'Groups cannot carry an opening balance - enter it on the ledger.']);
            }
            if ($existing && $existing['is_system'] && $existing['account_type'] !== $d['account_type']) {
                throw new ValidationException(['parent_id' => 'System accounts cannot move to another account type.']);
            }
            if ($existing && $existing['account_type'] !== $d['account_type'] && Database::value('SELECT COUNT(*) FROM journal_lines WHERE account_id = ?', [$existing['id']])) {
                throw new ValidationException(['parent_id' => 'This account has transactions and cannot change its account type.']);
            }
            $d['sub_type'] = ($d['sub_type'] ?? '') ?: null;
            $d['opening_type'] = ($d['opening_type'] ?? '') ?: 'Dr';
            return $d;
        },
        'before_delete' => static function (array $row): ?string {
            if ($row['is_system']) {
                return 'System accounts cannot be deleted. You can mark them inactive.';
            }
            if (Database::value('SELECT COUNT(*) FROM accounts WHERE parent_id = ?', [$row['id']])) {
                return 'This group has sub-accounts. Move or delete them first.';
            }
            if (Database::value('SELECT COUNT(*) FROM journal_lines WHERE account_id = ?', [$row['id']])) {
                return 'This account has transactions and cannot be deleted. Mark it inactive instead.';
            }
            return null;
        },
    ],
    'can_delete_row' => static fn ($r) => !$r['is_system'],
];
