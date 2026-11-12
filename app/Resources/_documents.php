<?php
/** Shared list definition for quotes, invoices, credit notes and purchase orders. */
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Services\DocumentService as Docs;

return static function (string $type): array {
    $cfg = Docs::TYPES[$type];
    $statuses = [
        'quote' => ['draft', 'sent', 'accepted', 'declined', 'converted', 'void'],
        'invoice' => ['draft', 'posted', 'partly_paid', 'paid', 'void'],
        'credit_note' => ['posted', 'partly_paid', 'paid', 'void'],
        'po' => ['draft', 'open', 'partial', 'received', 'closed', 'void'],
    ][$type];
    $map = array_intersect_key(Docs::STATUS, array_flip($statuses));
    $cid = static fn () => Auth::instance()->companyId();
    $is = static fn (array $st) => static fn ($r) => in_array($r['status'] ?? '', $st, true);
    $isSale = $cfg['contact'] === 'customer';

    $actions = [];
    if ($type === 'invoice') {
        $actions['post'] = ['label' => 'Post invoice', 'icon' => 'circle-check', 'tone' => 'emerald', 'permission' => 'edit', 'confirm' => 'Post this invoice? Stock is issued and the ledger is updated. Posted invoices cannot be edited.',
            'visible' => $is(['draft']), 'handler' => static function ($row) { Docs::postInvoice((int) $row['id']); return 'Invoice ' . $row['number'] . ' posted.'; }];
    }
    if ($type === 'po') {
        $actions['approve'] = ['label' => 'Approve', 'icon' => 'circle-check', 'tone' => 'emerald', 'permission' => 'edit', 'visible' => $is(['draft']),
            'handler' => static function ($row) { Database::update('documents', ['status' => 'open'], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]); return 'Purchase order approved - ready to receive goods.'; }];
        $actions['close'] = ['label' => 'Close (no more deliveries)', 'icon' => 'circle-slash', 'permission' => 'edit', 'confirm' => 'Close this purchase order? Remaining quantities will not be received.', 'visible' => $is(['open', 'partial']),
            'handler' => static function ($row) { Database::update('documents', ['status' => 'closed'], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]); return 'Purchase order closed.'; }];
    }
    if ($type === 'quote') {
        foreach (['sent' => ['Mark as sent', ['draft']], 'accepted' => ['Mark accepted', ['draft', 'sent']], 'declined' => ['Mark declined', ['draft', 'sent']]] as $st => [$label, $from]) {
            $actions['mark-' . $st] = ['label' => $label, 'icon' => 'check', 'permission' => 'edit', 'visible' => $is($from),
                'handler' => static function ($row) use ($st) { Database::update('documents', ['status' => $st], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]); return 'Quote updated.'; }];
        }
        $actions['convert'] = ['label' => 'Convert to invoice', 'icon' => 'receipt', 'tone' => 'emerald', 'permission' => 'edit', 'visible' => $is(['draft', 'sent', 'accepted']),
            'handler' => static function ($row) { $id = Docs::convertQuote((int) $row['id']); return ['message' => 'Draft invoice created from the quote.', 'redirect' => url('/sales/invoices/' . $id)]; }];
    }
    $actions['void'] = ['label' => 'Void', 'icon' => 'ban', 'tone' => 'rose', 'permission' => 'delete',
        'confirm' => 'Void this document? Its ledger entries and stock movements are reversed. This cannot be undone.',
        'visible' => $is(['posted', 'open', 'sent', 'accepted', 'draft']),
        'handler' => static function ($row) { Docs::voidDocument((int) $row['id']); return 'Document voided.'; }];

    $columns = [
        'doc_date' => ['label' => 'Date', 'sql' => 't.doc_date', 'format' => 'date', 'width' => 'w-28'],
        'number' => ['label' => $cfg['label'] . ' #', 'sql' => 't.number', 'format' => 'code'],
        'contact' => ['label' => $isSale ? 'Customer' : 'Vendor', 'sql' => 'c.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['contact']) . '</span>' . ($r['source_type'] === 'shop' ? ' <span class="badge badge-violet ml-1">Online</span>' : '')],
        'reference' => ['label' => 'Reference', 'sql' => 't.reference'],
        'due_date' => ['label' => $cfg['due'], 'sql' => 't.due_date', 'render' => static function ($r) {
            if (!$r['due_date']) { return ''; }
            $late = in_array($r['status'], ['posted', 'partly_paid'], true) && $r['due_date'] < date('Y-m-d');
            return '<span class="' . ($late ? 'font-semibold text-rose-600' : '') . '">' . e(fmt_date($r['due_date'])) . ($late ? ' · overdue' : '') . '</span>';
        }],
        'currency_code' => ['label' => 'Cur', 'sql' => 't.currency_code', 'format' => 'code'],
        'total' => ['label' => 'Total', 'sql' => 't.total', 'format' => 'money', 'align' => 'right'],
    ];
    if (in_array($type, ['invoice', 'credit_note'], true)) {
        $columns['balance'] = ['label' => $type === 'invoice' ? 'Balance due' : 'To refund', 'sql' => "CASE WHEN t.status IN ('posted','partly_paid') THEN t.total - t.amount_paid ELSE 0 END", 'format' => 'money', 'align' => 'right'];
    }
    $columns['status'] = ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => $map];

    $stats = [['label' => $cfg['plural'], 'sql' => 'COUNT(*)', 'icon' => 'file-text', 'tone' => 'slate']];
    if ($type === 'invoice') {
        $stats[] = ['label' => 'Invoiced (base)', 'sql' => "COALESCE(SUM(CASE WHEN t.status NOT IN ('draft','void') THEN t.total_base END),0)", 'format' => 'money', 'icon' => 'receipt', 'tone' => 'cyan'];
        $stats[] = ['label' => 'Outstanding', 'sql' => "COALESCE(SUM(CASE WHEN t.status IN ('posted','partly_paid') THEN (t.total - t.amount_paid) * t.exchange_rate END),0)", 'format' => 'money', 'icon' => 'hourglass', 'tone' => 'amber'];
        $stats[] = ['label' => 'Overdue', 'sql' => "COALESCE(SUM(t.status IN ('posted','partly_paid') AND t.due_date < CURRENT_DATE),0)", 'icon' => 'triangle-alert', 'tone' => 'rose'];
    } elseif ($type === 'po') {
        $stats[] = ['label' => 'Ordered (base)', 'sql' => "COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.total_base END),0)", 'format' => 'money', 'icon' => 'clipboard-list', 'tone' => 'cyan'];
        $stats[] = ['label' => 'Awaiting delivery', 'sql' => "COALESCE(SUM(t.status IN ('open','partial')),0)", 'icon' => 'truck', 'tone' => 'amber'];
        $stats[] = ['label' => 'Drafts', 'sql' => "COALESCE(SUM(t.status = 'draft'),0)", 'icon' => 'file-pen', 'tone' => 'slate'];
    } elseif ($type === 'quote') {
        $stats[] = ['label' => 'Quoted value (base)', 'sql' => "COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.total_base END),0)", 'format' => 'money', 'icon' => 'file-text', 'tone' => 'cyan'];
        $stats[] = ['label' => 'Open (sent / accepted)', 'sql' => "COALESCE(SUM(t.status IN ('sent','accepted')),0)", 'icon' => 'hourglass', 'tone' => 'amber'];
        $stats[] = ['label' => 'Won (invoiced)', 'sql' => "COALESCE(SUM(t.status = 'converted'),0)", 'icon' => 'circle-check', 'tone' => 'emerald'];
    } else {
        $stats[] = ['label' => 'Credited (base)', 'sql' => "COALESCE(SUM(CASE WHEN t.status <> 'void' THEN t.total_base END),0)", 'format' => 'money', 'icon' => 'file-minus', 'tone' => 'rose'];
    }

    return [
        'title' => $cfg['plural'],
        'singular' => $cfg['label'],
        'icon' => ['quote' => 'file-text', 'invoice' => 'receipt', 'credit_note' => 'file-minus', 'po' => 'clipboard-list'][$type],
        'description' => [
            'quote' => 'Price offers to customers. Convert an accepted quote into an invoice in one click.',
            'invoice' => 'Posting an invoice issues stock (FIFO cost) and updates receivables, sales and tax.',
            'credit_note' => 'Created automatically when returned goods are received. Refunds are recorded here.',
            'po' => 'Orders to vendors. Receiving goods adds stock batches at landed cost (price + freight) and updates payables.',
        ][$type],
        'table' => 'documents',
        'scope' => ['doc_type' => $type],
        'from' => 'documents t JOIN contacts c ON c.id = t.contact_id',
        'module' => $cfg['module'],
        'form_url' => $type === 'credit_note' ? null : '/' . $cfg['path'] . '/new',
        'can_create' => $type !== 'credit_note',
        'row_url' => '/' . $cfg['path'] . '/{id}',
        'columns' => $columns,
        'select_extra' => ['source_type' => 't.source_type'],
        'default_sort' => ['doc_date', 'desc'],
        'search' => ['t.number', 't.reference', 'c.name', 'c.code'],
        'filters' => [
            'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], $map)],
            'date' => ['label' => 'Date', 'type' => 'daterange', 'sql' => 't.doc_date'],
            'contact_id' => ['label' => $isSale ? 'Customer' : 'Vendor', 'type' => 'select', 'dropdown' => true, 'sql' => 't.contact_id', 'options' => static fn () => array_column(Database::all('SELECT id, name FROM contacts WHERE company_id = ? AND contact_type = ? ORDER BY name', [$cid(), $cfg['contact']]), 'name', 'id')],
            'total' => ['label' => 'Total', 'type' => 'numrange', 'sql' => 't.total'],
            'source_type' => ['label' => 'Source', 'type' => 'select', 'sql' => 't.source_type', 'options' => ['manual' => 'Entered by staff', 'shop' => 'Online shop', 'quote' => 'From quote', 'shop_return' => 'Online return']],
            'currency_code' => ['label' => 'Currency', 'type' => 'select', 'sql' => 't.currency_code', 'options' => static fn () => array_column(Database::all('SELECT code FROM currencies WHERE company_id = ? ORDER BY code', [$cid()]), 'code', 'code')],
        ],
        'stats' => $stats,
        'fields' => [],
        'can_edit_row' => static fn ($r) => ($r['status'] ?? '') === 'draft',
        'can_delete_row' => static fn ($r) => ($r['status'] ?? '') === 'draft',
        'hooks' => ['before_delete' => static fn ($row) => $row['status'] !== 'draft' ? 'Only drafts can be deleted. Void the document instead.' : null],
        'row_actions' => $actions,
    ];
};
