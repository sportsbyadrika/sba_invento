<?php
$amt = static fn (float $v) => abs($v) < 0.005 ? '' : money($v);
$diff = round($tot['cb_dr'] - $tot['cb_cr'], 2);
?>
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2 text-sm">
  <span class="text-slate-600">Period <span class="font-semibold text-slate-900"><?= e(fmt_date($from)) ?></span> to <span class="font-semibold text-slate-900"><?= e(fmt_date($to)) ?></span></span>
  <?php if (abs($diff) < 0.01): ?><span class="badge badge-green">Balanced</span><?php else: ?><span class="badge badge-rose">Out of balance by <?= money(abs($diff)) ?> — check opening balances</span><?php endif; ?>
</div>
<div class="overflow-x-auto">
<table class="erp-table">
  <thead>
    <tr><th rowspan="2">Account</th><th rowspan="2" class="w-24">Code</th><th colspan="2" class="text-center">Opening</th><th colspan="2" class="text-center">Transactions</th><th colspan="2" class="text-center">Closing</th></tr>
    <tr><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr>
  </thead>
  <tbody>
  <?php if (!$ids): ?><tr><td colspan="8" class="py-10 text-center text-slate-500">No balances for this period.</td></tr><?php endif; ?>
  <?php $indent = ['', 'pl-5', 'pl-10', 'pl-14', 'pl-20', 'pl-24']; foreach ($ids as $id): $r = $map[$id]; $g = $r['is_group']; ?>
    <tr class="<?= $g ? 'bg-slate-50/60 font-semibold text-slate-900' : '' ?>">
      <td class="<?= $indent[min($opts['groups'] ? $r['depth'] : 0, 5)] ?>"><?php if (!$g): ?><a class="hover:text-cyan-700" href="<?= e(url('/reports/ledger', ['account_id' => $id, 'from' => $from, 'to' => $to])) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
      <td class="font-mono text-xs text-slate-500"><?= e($r['code']) ?></td>
      <td class="text-right tabular-nums"><?= $amt(max($r['ob'], 0)) ?></td>
      <td class="text-right tabular-nums"><?= $amt(max(-$r['ob'], 0)) ?></td>
      <td class="text-right tabular-nums"><?= $amt($r['dr']) ?></td>
      <td class="text-right tabular-nums"><?= $amt($r['cr']) ?></td>
      <td class="text-right tabular-nums"><?= $amt(max($r['cb'], 0)) ?></td>
      <td class="text-right tabular-nums"><?= $amt(max(-$r['cb'], 0)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="bg-slate-100 font-bold text-slate-900">
      <td class="px-3 py-2" colspan="2">Grand total (ledgers)</td>
      <td class="px-3 py-2 text-right tabular-nums"><?= money($tot['ob_dr']) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= money($tot['ob_cr']) ?></td>
      <td class="px-3 py-2 text-right tabular-nums"><?= money($tot['dr']) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= money($tot['cr']) ?></td>
      <td class="px-3 py-2 text-right tabular-nums"><?= money($tot['cb_dr']) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= money($tot['cb_cr']) ?></td>
    </tr>
  </tfoot>
</table>
</div>
