<?php if (!$account): ?>
  <div class="p-10 text-center text-slate-500"><?= icon('book-open', 'size-8 mx-auto mb-2 text-slate-300') ?>Select a ledger account in the options panel and run the report.</div>
<?php return; endif;
$bal = $opening; $tdr = $tcr = 0.0;
$fmt = static fn (float $v) => money(abs($v)) . ' <span class="text-[10px] font-semibold text-slate-400">' . ($v >= 0 ? 'Dr' : 'Cr') . '</span>';
?>
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2 text-sm">
  <span><span class="font-mono text-xs text-slate-500"><?= e($account['code']) ?></span> <span class="font-bold text-slate-900"><?= e($account['name']) ?></span></span>
  <span class="text-slate-600"><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></span>
</div>
<div class="overflow-x-auto">
<table class="erp-table">
  <thead><tr><th class="w-28">Date</th><th class="w-32">Voucher</th><th>Particulars (contra account)</th><th>Narration</th><th class="w-36 text-right">Debit</th><th class="w-36 text-right">Credit</th><th class="w-40 text-right">Balance</th></tr></thead>
  <tbody>
    <tr class="bg-slate-50 font-semibold"><td><?= e(fmt_date($from)) ?></td><td></td><td colspan="2">Opening balance</td><td></td><td></td><td class="text-right tabular-nums"><?= $fmt($opening) ?></td></tr>
    <?php if (!$lines): ?><tr><td colspan="7" class="py-8 text-center text-slate-500">No posted transactions in this period.</td></tr><?php endif; ?>
    <?php foreach ($lines as $l): $bal += (float) $l['debit_base'] - (float) $l['credit_base']; $tdr += (float) $l['debit_base']; $tcr += (float) $l['credit_base']; ?>
      <tr class="cursor-pointer" data-href="<?= e(url('/accountant/journals/' . $l['journal_id'])) ?>">
        <td><?= e(fmt_date($l['journal_date'])) ?></td>
        <td class="font-mono text-xs font-semibold"><?= e($l['number']) ?></td>
        <td><?= e($l['contra']) ?></td>
        <td class="max-w-xs truncate text-slate-500"><?= e($l['description'] ?: $l['narration']) ?></td>
        <td class="text-right tabular-nums"><?= (float) $l['debit_base'] ? money($l['debit_base']) : '' ?></td>
        <td class="text-right tabular-nums"><?= (float) $l['credit_base'] ? money($l['credit_base']) : '' ?></td>
        <td class="text-right tabular-nums"><?= $fmt($bal) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr class="bg-slate-100 font-bold text-slate-900"><td colspan="4" class="px-3 py-2">Total / Closing balance</td><td class="px-3 py-2 text-right tabular-nums"><?= money($tdr) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= money($tcr) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= $fmt($bal) ?></td></tr></tfoot>
</table>
</div>
