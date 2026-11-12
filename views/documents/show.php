<?php
/** Document view with actions. @var array $doc @var array $cfg @var string $type */
use App\Services\DocumentService as Docs;
$company = auth()->company();
$isSale = $cfg['contact'] === 'customer';
$isBase = $doc['currency_code'] === $company['base_currency'];
$st = $doc['status'];
$canEdit = can($cfg['module'], 'edit');
$due = round((float) $doc['total'] - (float) $doc['amount_paid'], 2);
$m = static fn ($v) => money($v);
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div>
    <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?><?= e($isSale ? 'Sales' : 'Purchases') ?><?= icon('chevron-right', 'size-3') ?><a href="<?= e(url('/' . $cfg['path'])) ?>"><?= e($cfg['plural']) ?></a></nav>
    <h1 class="mt-1 flex flex-wrap items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= e($cfg['label']) ?> <span class="font-mono"><?= e($doc['number']) ?></span> <?= App\Core\Resource::badge(Docs::STATUS, $st) ?>
      <?php if ($doc['source_type'] === 'shop'): ?><span class="badge badge-violet">Online order</span><?php endif; ?></h1>
  </div>
  <div class="no-print flex flex-wrap gap-2" data-journal-actions data-action-url="<?= e(url('/' . $cfg['path'] . '/action')) ?>">
    <a href="<?= e(url('/' . $cfg['path'])) ?>" class="btn"><?= icon('arrow-left', 'size-4') ?>Back</a>
    <a href="<?= e(url('/' . $cfg['path'] . '/' . $doc['id'] . '/print')) ?>" class="btn" target="_blank" rel="noopener"><?= icon('printer', 'size-4') ?>Print / PDF</a>
    <?php if ($type !== 'credit_note' && can($cfg['module'], 'create')): ?><a href="<?= e(url('/' . $cfg['path'] . '/new', ['copy' => $doc['id']])) ?>" class="btn"><?= icon('copy', 'size-4') ?>Duplicate</a><?php endif; ?>
    <?php if ($canEdit): ?>
      <?php if ($st === 'draft' && $type !== 'credit_note'): ?><a href="<?= e(url('/' . $cfg['path'] . '/' . $doc['id'] . '/edit')) ?>" class="btn"><?= icon('pencil', 'size-4') ?>Edit</a><?php endif; ?>
      <?php if ($type === 'invoice' && $st === 'draft'): ?><button type="button" class="btn btn-success" data-row-action="post" data-id="<?= (int) $doc['id'] ?>" data-confirm="Post this invoice? Stock is issued and the ledger is updated."><?= icon('circle-check', 'size-4') ?>Post invoice</button><?php endif; ?>
      <?php if ($type === 'po' && $st === 'draft'): ?><button type="button" class="btn btn-success" data-row-action="approve" data-id="<?= (int) $doc['id'] ?>"><?= icon('circle-check', 'size-4') ?>Approve</button><?php endif; ?>
      <?php if ($type === 'po' && in_array($st, ['open', 'partial'], true)): ?><a href="<?= e(url('/purchases/orders/' . $doc['id'] . '/receive')) ?>" class="btn btn-success"><?= icon('package-check', 'size-4') ?>Receive goods</a><?php endif; ?>
      <?php if ($type === 'quote' && in_array($st, ['draft', 'sent', 'accepted'], true)): ?><button type="button" class="btn btn-success" data-row-action="convert" data-id="<?= (int) $doc['id'] ?>" data-confirm="Create a draft invoice from this quote?"><?= icon('receipt', 'size-4') ?>Convert to invoice</button><?php endif; ?>
      <?php if (in_array($type, ['invoice', 'credit_note'], true) && in_array($st, ['posted', 'partly_paid'], true)): ?><button type="button" class="btn btn-primary" data-toggle="#payment-form"><?= icon('banknote', 'size-4') ?><?= $type === 'invoice' ? 'Record payment' : 'Record refund' ?></button><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($canEdit && in_array($type, ['invoice', 'credit_note'], true) && in_array($st, ['posted', 'partly_paid'], true)): ?>
<form id="payment-form" class="card mb-4 hidden p-4" method="post" action="<?= e(url('/' . $cfg['path'] . '/' . $doc['id'] . '/payment')) ?>" data-ajax-form data-redirect-on-success data-enter-nav>
  <?= csrf_field() ?>
  <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
  <div class="grid gap-3 sm:grid-cols-5">
    <div><label class="label" for="pay_amount">Amount (<?= e($doc['currency_code']) ?>)</label><input class="input text-right tabular-nums" id="pay_amount" name="amount" value="<?= e(number_format($due, 2, '.', '')) ?>"><p class="field-error hidden" data-error-for="amount"></p></div>
    <div><label class="label" for="pay_date">Date</label><input type="date" class="input" id="pay_date" name="date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="sm:col-span-2"><label class="label" for="pay_acc"><?= $type === 'invoice' ? 'Received into' : 'Paid from' ?></label>
      <select class="input" id="pay_acc" name="account_id"><?php foreach ($cashAccounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?></select><p class="field-error hidden" data-error-for="account_id"></p></div>
    <div><label class="label" for="pay_ref">Reference</label><input class="input" id="pay_ref" name="reference" maxlength="60" placeholder="Cheque / transfer no."></div>
  </div>
  <div class="mt-3 flex justify-end"><button type="submit" class="btn btn-primary" data-save-btn><?= icon('check', 'size-4') ?>Save <?= $type === 'invoice' ? 'payment' : 'refund' ?></button></div>
</form>
<?php endif; ?>

<div class="grid gap-4 xl:grid-cols-3">
  <section class="card p-5 xl:col-span-2">
    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
      <div class="sm:col-span-2"><dt class="label"><?= $isSale ? 'Customer' : 'Vendor' ?></dt><dd class="font-semibold text-slate-900"><?= e($doc['contact_name']) ?> <span class="font-mono text-xs text-slate-500"><?= e($doc['contact_code']) ?></span></dd>
        <?php if ($doc['billing_address']): ?><dd class="mt-1 whitespace-pre-line text-slate-600"><?= e($doc['billing_address']) ?></dd><?php endif; ?></div>
      <div><dt class="label">Date</dt><dd class="text-slate-900"><?= e(fmt_date($doc['doc_date'])) ?></dd></div>
      <div><dt class="label"><?= e($cfg['due']) ?></dt><dd class="text-slate-900"><?= e(fmt_date($doc['due_date']) ?: '—') ?></dd></div>
      <div><dt class="label">Reference</dt><dd class="text-slate-900"><?= e($doc['reference'] ?: '—') ?></dd></div>
      <div><dt class="label">Currency</dt><dd class="font-mono text-slate-900"><?= e($doc['currency_code']) ?><?= $isBase ? '' : ' @ ' . e(rtrim(rtrim((string) $doc['exchange_rate'], '0'), '.')) ?></dd></div>
      <div><dt class="label">Created by</dt><dd class="text-slate-900"><?= e($doc['created_by_name'] ?? '—') ?></dd></div>
      <?php if ($doc['journal_id']): ?><div><dt class="label">Ledger entry</dt><dd><a class="font-semibold text-cyan-700 hover:underline" href="<?= e(url('/accountant/journals/' . $doc['journal_id'])) ?>">View journal</a></dd></div><?php endif; ?>
    </dl>
  </section>
  <section class="card p-5">
    <dl class="space-y-1.5 text-sm">
      <div class="flex justify-between"><dt class="text-slate-500">Sub total</dt><dd class="tabular-nums"><?= $m($doc['subtotal']) ?></dd></div>
      <?php if ((float) $doc['discount_total']): ?><div class="flex justify-between"><dt class="text-slate-500">Discount given</dt><dd class="tabular-nums text-slate-600"><?= $m($doc['discount_total']) ?></dd></div><?php endif; ?>
      <?php foreach ($taxSummary as [$lbl, $pct, $amt]): ?><div class="flex justify-between"><dt class="text-slate-500"><?= e($lbl) ?> <?= e(rtrim(rtrim(number_format($pct, 3), '0'), '.')) ?>%</dt><dd class="tabular-nums"><?= $m($amt) ?></dd></div><?php endforeach; ?>
      <?php if ((float) $doc['freight']): ?><div class="flex justify-between"><dt class="text-slate-500"><?= $isSale ? 'Delivery charge' : 'Freight' ?></dt><dd class="tabular-nums"><?= $m($doc['freight']) ?></dd></div><?php endif; ?>
      <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-extrabold text-slate-900"><dt>Total</dt><dd class="tabular-nums"><?= e($doc['currency_code']) ?> <?= $m($doc['total']) ?></dd></div>
      <?php if (in_array($type, ['invoice', 'credit_note'], true) && !in_array($st, ['draft', 'void'], true)): ?>
        <div class="flex justify-between"><dt class="text-slate-500"><?= $type === 'invoice' ? 'Paid' : 'Refunded' ?></dt><dd class="tabular-nums text-emerald-700"><?= $m($doc['amount_paid']) ?></dd></div>
        <div class="flex justify-between font-bold"><dt><?= $type === 'invoice' ? 'Balance due' : 'To refund' ?></dt><dd class="tabular-nums <?= $due > 0 ? 'text-rose-600' : 'text-emerald-700' ?>"><?= $m($due) ?></dd></div>
      <?php endif; ?>
    </dl>
  </section>
</div>

<div class="card mt-4 overflow-x-auto">
  <table class="erp-table">
    <thead><tr><th class="w-8">#</th><th>Item</th><th class="text-right">Qty</th><?php if ($type === 'po'): ?><th class="text-right">Received</th><?php endif; ?><th class="text-right">Rate</th><th class="text-right">Disc %</th><th class="text-right">Tax</th><th class="text-right">Amount</th><?php if ($type === 'invoice' && $st !== 'draft'): ?><th class="text-right">Cost (FIFO)</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($doc['lines'] as $l): ?>
      <tr>
        <td class="font-mono text-xs text-slate-400"><?= (int) $l['line_no'] ?></td>
        <td><?php if ($l['item_id']): ?><span class="font-mono text-xs text-slate-500"><?= e($l['sku']) ?></span> <span class="font-medium text-slate-900"><?= e($l['item_name']) ?></span><?php endif; ?><?php if ($l['description']): ?><span class="block text-xs text-slate-500"><?= e($l['description']) ?></span><?php endif; ?></td>
        <td class="text-right tabular-nums"><?= e(rtrim(rtrim(number_format((float) $l['qty'], 3), '0'), '.')) ?> <span class="text-xs text-slate-400"><?= e($l['unit']) ?></span></td>
        <?php if ($type === 'po'): ?><td class="text-right tabular-nums <?= (float) $l['received_qty'] >= (float) $l['qty'] ? 'text-emerald-700' : 'text-amber-700' ?>"><?= e(rtrim(rtrim(number_format((float) $l['received_qty'], 3), '0'), '.') ?: '0') ?></td><?php endif; ?>
        <td class="text-right tabular-nums"><?= $m($l['unit_price']) ?></td>
        <td class="text-right tabular-nums"><?= (float) $l['discount_pct'] ? e(rtrim(rtrim((string) $l['discount_pct'], '0'), '.')) : '' ?></td>
        <td class="text-right tabular-nums text-slate-600"><?= (float) $l['tax_pct'] ? e(rtrim(rtrim((string) $l['tax_pct'], '0'), '.')) . '% · ' . $m($l['tax_amount']) : '—' ?></td>
        <td class="text-right tabular-nums font-medium"><?= $m($l['line_total']) ?></td>
        <?php if ($type === 'invoice' && $st !== 'draft'): ?><td class="text-right tabular-nums text-slate-500"><?= (float) $l['cost_total'] ? $m($l['cost_total']) : '' ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($doc['notes'] || $doc['terms']): ?>
<div class="mt-4 grid gap-4 md:grid-cols-2">
  <?php if ($doc['notes']): ?><div class="card p-4 text-sm"><div class="label">Notes</div><p class="whitespace-pre-line text-slate-700"><?= e($doc['notes']) ?></p></div><?php endif; ?>
  <?php if ($doc['terms']): ?><div class="card p-4 text-sm"><div class="label">Terms</div><p class="whitespace-pre-line text-slate-700"><?= e($doc['terms']) ?></p></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($receipts): ?>
<section class="card mt-4 overflow-x-auto">
  <h2 class="border-b border-slate-200 px-4 py-2 text-sm font-bold text-slate-900">Goods received</h2>
  <table class="erp-table"><thead><tr><th>Date</th><th>Reference</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Landed unit cost</th></tr></thead><tbody>
  <?php foreach ($receipts as $r): ?><tr><td><?= e(fmt_date($r['move_date'])) ?></td><td class="font-mono text-xs"><?= e($r['reference']) ?></td><td><?= e($r['name']) ?></td><td class="text-right tabular-nums"><?= e(rtrim(rtrim(number_format((float) $r['qty'], 3), '0'), '.')) ?></td><td class="text-right tabular-nums"><?= money($r['unit_cost']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>

<?php if ($payments || $related): ?>
<section class="card mt-4 overflow-x-auto">
  <h2 class="border-b border-slate-200 px-4 py-2 text-sm font-bold text-slate-900">Related</h2>
  <table class="erp-table"><tbody>
  <?php foreach ($payments as $p): ?><tr data-href="<?= e(url('/accountant/journals/' . $p['id'])) ?>" class="cursor-pointer"><td class="w-28"><?= e(fmt_date($p['journal_date'])) ?></td><td><span class="badge badge-green">Payment</span> <?= e($p['narration']) ?></td><td class="font-mono text-xs"><?= e($p['number']) ?></td><td class="text-right tabular-nums"><?= money($p['total_base']) ?> <?= e($company['base_currency']) ?></td></tr><?php endforeach; ?>
  <?php foreach ($related as $r): $rc = Docs::TYPES[$r['doc_type']]; ?><tr data-href="<?= e(url('/' . $rc['path'] . '/' . $r['id'])) ?>" class="cursor-pointer"><td class="w-28"></td><td><span class="badge badge-violet"><?= e($rc['label']) ?></span></td><td class="font-mono text-xs"><?= e($r['number']) ?></td><td class="text-right tabular-nums"><?= money($r['total']) ?> · <?= App\Core\Resource::badge(Docs::STATUS, $r['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>
