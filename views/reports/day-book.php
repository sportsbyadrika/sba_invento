<?php if (!$vouchers): ?>
  <div class="p-10 text-center text-slate-500">No vouchers between <?= e(fmt_date($from)) ?> and <?= e(fmt_date($to)) ?>.</div>
<?php return; endif; ?>
<div class="overflow-x-auto">
<table class="erp-table">
  <thead><tr><th class="w-28">Date</th><th class="w-32">Voucher</th><th>Account</th><th class="w-36 text-right">Debit</th><th class="w-36 text-right">Credit</th></tr></thead>
  <tbody>
  <?php foreach ($vouchers as $id => $v): $h = $v['head']; ?>
    <tr class="cursor-pointer bg-slate-50/70" data-href="<?= e(url('/accountant/journals/' . $id)) ?>">
      <td class="font-semibold"><?= e(fmt_date($h['journal_date'])) ?></td>
      <td class="font-mono text-xs font-semibold"><?= e($h['number']) ?> <?= $h['status'] === 'draft' ? '<span class="badge badge-amber">Draft</span>' : '' ?></td>
      <td colspan="3" class="text-slate-500"><?= e($h['narration']) ?></td>
    </tr>
    <?php foreach ($v['lines'] as $l): ?>
      <tr><td></td><td></td><td class="pl-6"><span class="font-mono text-xs text-slate-400"><?= e($l['code']) ?></span> <?= e($l['name']) ?></td>
        <td class="text-right tabular-nums"><?= (float) $l['debit_base'] ? money($l['debit_base']) : '' ?></td><td class="text-right tabular-nums"><?= (float) $l['credit_base'] ? money($l['credit_base']) : '' ?></td></tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
