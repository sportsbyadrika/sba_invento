<?php
/** Payments received against invoices (incl. COD collected on delivery). Recorded from the invoice screen. */
return [
    'title' => 'Payments Received',
    'singular' => 'Payment',
    'icon' => 'hand-coins',
    'description' => 'Customer payments recorded on invoices, including cash collected for online COD orders. Record a payment from the invoice screen.',
    'table' => 'journals',
    'from' => "journals t JOIN documents d ON d.id = t.source_id AND d.company_id = t.company_id JOIN contacts c ON c.id = d.contact_id",
    'where' => static fn () => ["t.source_type = 'receipt' AND t.status = 'posted'", []],
    'readonly' => true, 'can_create' => false, 'can_delete' => false,
    'row_url' => '/sales/invoices/{doc_id}',
    'columns' => [
        'journal_date' => ['label' => 'Date', 'sql' => 't.journal_date', 'format' => 'date'],
        'number' => ['label' => 'Voucher', 'sql' => 't.number', 'format' => 'code'],
        'invoice' => ['label' => 'Invoice', 'sql' => 'd.number', 'format' => 'code'],
        'customer' => ['label' => 'Customer', 'sql' => 'c.name', 'render' => static fn ($r) => e($r['customer']) . ($r['src'] === 'shop' ? ' <span class="badge badge-violet ml-1">Online COD</span>' : '')],
        'narration' => ['label' => 'Details', 'sql' => 't.narration'],
        'amount' => ['label' => 'Amount (base)', 'sql' => 't.total_base', 'format' => 'money', 'align' => 'right'],
    ],
    'select_extra' => ['doc_id' => 'd.id', 'src' => 'd.source_type'],
    'default_sort' => ['journal_date', 'desc'],
    'search' => ['t.number', 'd.number', 'c.name', 't.narration'],
    'filters' => [
        'date' => ['label' => 'Date', 'type' => 'daterange', 'sql' => 't.journal_date'],
        'source' => ['label' => 'Source', 'type' => 'select', 'sql' => 'd.source_type', 'options' => ['manual' => 'Invoices', 'shop' => 'Online orders (COD)', 'quote' => 'From quotes']],
        'amount' => ['label' => 'Amount', 'type' => 'numrange', 'sql' => 't.total_base'],
    ],
    'stats' => [
        ['label' => 'Payments', 'sql' => 'COUNT(*)', 'icon' => 'hand-coins'],
        ['label' => 'Collected (base)', 'sql' => 'COALESCE(SUM(t.total_base),0)', 'format' => 'money', 'icon' => 'wallet', 'tone' => 'emerald'],
        ['label' => 'Online COD', 'sql' => "COALESCE(SUM(CASE WHEN d.source_type = 'shop' THEN t.total_base END),0)", 'format' => 'money', 'icon' => 'banknote', 'tone' => 'violet'],
    ],
    'fields' => [],
];
