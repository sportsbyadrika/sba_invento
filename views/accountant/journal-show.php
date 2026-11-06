<?php
$company = auth()->company();
$isBase = $j['currency_code'] === $company['base_currency'];
$status = ['draft' => ['Draft', 'amber'], 'posted' => ['Posted', 'green'], 'void' => ['Void', 'slate']];
$tdr = $tcr = 0;
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div>
    <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?>Accountant<?= icon('chevron-right', 'size-3') ?><a href="<?= e(url('/accountant/journals')) ?>">Manual Journals</a></nav>
    <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('book-open', 'size-6 text-cyan-700') ?>Journal <span class="font-mono"><?= e($j['number']) ?></span> <?= App\Core\Resource::badge($status, $j['status']) ?></h1>
  </div>
  <div class="no-print flex flex-wrap gap-2" data-journal-actions data-action-url="<?= e(url('/accountant/journals/action')) ?>">
    <a href="<?= e(url('/accountant/journals')) ?>" class="btn"><?= icon('arrow-left', 'size-4') ?>Back</a>
    <button type="button" class="btn" data-print><?= icon('printer', 'size-4') ?>Print</button>
    <?php if (can('accountant', 'create')): ?><a href="<?= e(url('/accountant/journals/new', ['copy' => $j['id']])) ?>" class="btn"><?= icon('copy', 'size-4') ?>Duplicate</a><?php endif; ?>
    <?php if ($j['status'] === 'draft' && can('accountant', 'edit')): ?>
      <a href="<?= e(url('/accountant/journals/' . $j['id'] . '/edit')) ?>" class="btn"><?= icon('pencil', 'size-4') ?>Edit</a>
      <button type="button" class="btn btn-success" data-row-action="post" data-id="<?= (int) $j['id'] ?>" data-confirm="Post this journal to the ledger?"><?= icon('circle-check', 'size-4') ?>Post</button>
    <?php elseif ($j['status'] === 'posted' && can('accountant', 'delete') && $j['source_type'] === 'manual'): ?>
      <button type="button" class="btn btn-danger" data-row-action="void" data-id="<?= (int) $j['id'] ?>" data-confirm="Void this journal? Its amounts will be removed from the ledger."><?= icon('ban', 'size-4') ?>Void</button>
    <?php endif; ?>
  </div>
</div>

<div class="card p-5">
  <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
    <div><dt class="label">Date</dt><dd class="font-semibold text-slate-900"><?= e(fmt_date($j['journal_date'])) ?></dd></div>
    <div><dt class="label">Reference</dt><dd class="text-slate-900"><?= e($j['reference'] ?: '—') ?></dd></div>
    <div><dt class="label">Currency</dt><dd class="font-mono text-slate-900"><?= e($j['currency_code']) ?><?= $isBase ? '' : ' @ ' . e(rtrim(rtrim((string) $j['exchange_rate'], '0'), '.')) . ' ' . e($company['base_currency']) ?></dd></div>
    <div><dt class="label">Created</dt><dd class="text-slate-900"><?= e($j['created_by_name'] ?? '—') ?> <span class="block text-xs text-slate-500"><?= e(local_dt($j['created_at'])) ?></span></dd></div>
    <div><dt class="label">Posted</dt><dd class="text-slate-900"><?= $j['posted_at'] ? e($j['posted_by_name']) . '<span class="block text-xs text-slate-500">' . e(local_dt($j['posted_at'])) . '</span>' : '—' ?></dd></div>
    <div class="sm:col-span-2 lg:col-span-5"><dt class="label">Narration</dt><dd class="text-slate-900"><?= e($j['narration'] ?: '—') ?></dd></div>
  </dl>
</div>

<div class="card mt-4 overflow-x-auto">
  <table class="erp-table">
    <thead><tr><th class="w-10">#</th><th>Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><?php if (!$isBase): ?><th class="text-right">Debit (<?= e($company['base_currency']) ?>)</th><th class="text-right">Credit (<?= e($company['base_currency']) ?>)</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($j['lines'] as $l): $tdr += $l['debit']; $tcr += $l['credit']; ?>
        <tr>
          <td class="font-mono text-xs text-slate-400"><?= (int) $l['line_no'] ?></td>
          <td><a class="hover:text-cyan-700" href="<?= e(url('/reports/ledger', ['account_id' => $l['account_id']])) ?>"><span class="font-mono text-xs text-slate-500"><?= e($l['account_code']) ?></span> <?= e($l['account_name']) ?></a></td>
          <td class="text-slate-600"><?= e($l['description']) ?></td>
          <td class="text-right tabular-nums"><?= (float) $l['debit'] ? money($l['debit']) : '' ?></td>
          <td class="text-right tabular-nums"><?= (float) $l['credit'] ? money($l['credit']) : '' ?></td>
          <?php if (!$isBase): ?><td class="text-right tabular-nums text-slate-500"><?= (float) $l['debit_base'] ? money($l['debit_base']) : '' ?></td><td class="text-right tabular-nums text-slate-500"><?= (float) $l['credit_base'] ? money($l['credit_base']) : '' ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="bg-slate-50 font-bold"><td colspan="3" class="px-3 py-2 text-right text-xs uppercase text-slate-500">Total</td><td class="px-3 py-2 text-right tabular-nums"><?= money($tdr) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= money($tcr) ?></td><?php if (!$isBase): ?><td class="px-3 py-2 text-right tabular-nums" colspan="2"><?= money($j['total_base']) ?> <?= e($company['base_currency']) ?></td><?php endif; ?></tr></tfoot>
  </table>
</div>
