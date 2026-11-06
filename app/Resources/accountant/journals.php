<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Services\JournalService;

$status = ['draft' => ['Draft', 'amber'], 'posted' => ['Posted', 'green'], 'void' => ['Void', 'slate']];

return [
    'title' => 'Manual Journals',
    'singular' => 'Journal',
    'icon' => 'book-open',
    'description' => 'Double-entry vouchers. Drafts can be edited; posted journals update the ledger and are locked.',
    'table' => 'journals',
    'from' => 'journals t LEFT JOIN users u ON u.id = t.created_by',
    'form_url' => '/accountant/journals/new',
    'row_url' => '/accountant/journals/{id}',
    'columns' => [
        'journal_date' => ['label' => 'Date', 'sql' => 't.journal_date', 'format' => 'date', 'width' => 'w-28'],
        'number' => ['label' => 'Journal #', 'sql' => 't.number', 'format' => 'code'],
        'reference' => ['label' => 'Reference', 'sql' => 't.reference'],
        'narration' => ['label' => 'Narration', 'sql' => 't.narration', 'render' => static fn ($r) => '<span class="line-clamp-1 max-w-md">' . e($r['narration']) . '</span>'],
        'currency_code' => ['label' => 'Cur', 'sql' => 't.currency_code', 'format' => 'code'],
        'total_amount' => ['label' => 'Amount', 'sql' => 't.total_amount', 'format' => 'money', 'align' => 'right'],
        'total_base' => ['label' => 'Amount (Base)', 'sql' => 't.total_base', 'format' => 'money', 'align' => 'right'],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => $status],
        'created_by' => ['label' => 'Created By', 'sql' => 'u.name'],
    ],
    'default_sort' => ['journal_date', 'desc'],
    'search' => ['t.number', 't.reference', 't.narration'],
    'filters' => [
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], $status)],
        'date' => ['label' => 'Journal Date', 'type' => 'daterange', 'sql' => 't.journal_date'],
        'amount' => ['label' => 'Amount (Base)', 'type' => 'numrange', 'sql' => 't.total_base'],
        'currency_code' => ['label' => 'Currency', 'type' => 'select', 'sql' => 't.currency_code', 'options' => static fn () => array_column(Database::all('SELECT code FROM currencies WHERE company_id = ? ORDER BY code', [Auth::instance()->companyId()]), 'code', 'code')],
        'account' => ['label' => 'Contains Account', 'type' => 'select', 'in_template' => 'EXISTS (SELECT 1 FROM journal_lines jl WHERE jl.journal_id = t.id AND jl.account_id IN ({in}))', 'sql' => '', 'options' => static fn () => array_column(Database::all("SELECT id, CONCAT(code, ' · ', name) label FROM accounts WHERE company_id = ? AND is_group = 0 ORDER BY code", [Auth::instance()->companyId()]), 'label', 'id')],
        'created_by' => ['label' => 'Created By', 'type' => 'select', 'sql' => 't.created_by', 'options' => static fn () => array_column(Database::all('SELECT u.id, u.name FROM users u JOIN user_companies uc ON uc.user_id = u.id AND uc.company_id = ? ORDER BY u.name', [Auth::instance()->companyId()]), 'name', 'id')],
    ],
    'stats' => [
        ['label' => 'Journals', 'sql' => 'COUNT(*)', 'icon' => 'book-open', 'tone' => 'slate'],
        ['label' => 'Posted Value', 'sql' => "COALESCE(SUM(CASE WHEN t.status='posted' THEN t.total_base END),0)", 'format' => 'money', 'icon' => 'circle-check', 'tone' => 'emerald'],
        ['label' => 'Drafts', 'sql' => "COALESCE(SUM(t.status='draft'),0)", 'icon' => 'file-pen', 'tone' => 'amber', 'hint' => 'Not yet in the ledger'],
        ['label' => 'Foreign Currency', 'sql' => 'COALESCE(SUM(t.exchange_rate <> 1),0)', 'icon' => 'globe', 'tone' => 'indigo'],
    ],
    'readonly' => false,
    'fields' => [],
    'can_edit_row' => static fn ($r) => ($r['status'] ?? '') === 'draft',
    'can_delete_row' => static fn ($r) => ($r['status'] ?? '') === 'draft',
    'hooks' => [
        'before_delete' => static fn ($row) => $row['status'] !== 'draft' ? 'Only draft journals can be deleted. Void a posted journal instead.' : null,
    ],
    'row_actions' => [
        'post' => [
            'label' => 'Post', 'icon' => 'circle-check', 'tone' => 'emerald', 'permission' => 'edit',
            'confirm' => 'Post this journal to the ledger? Posted journals cannot be edited.',
            'visible' => static fn ($r) => ($r['status'] ?? '') === 'draft',
            'handler' => static function ($row) {
                JournalService::post((int) $row['id']);
                return 'Journal ' . $row['number'] . ' posted.';
            },
        ],
        'void' => [
            'label' => 'Void', 'icon' => 'ban', 'tone' => 'rose', 'permission' => 'delete',
            'confirm' => 'Void this posted journal? Its amounts will be removed from the ledger. This cannot be undone.',
            'visible' => static fn ($r) => ($r['status'] ?? '') === 'posted' && ($r['source_type'] ?? 'manual') === 'manual',
            'handler' => static function ($row) {
                if ($row['status'] !== 'posted') {
                    throw new ValidationException([], 'Only posted journals can be voided.');
                }
                Database::update('journals', ['status' => 'void'], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]);
                return 'Journal ' . $row['number'] . ' voided.';
            },
        ],
    ],
    'select_extra' => ['source_type' => 't.source_type'],
];
