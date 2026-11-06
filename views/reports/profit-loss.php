<?php
$indent = ['', 'pl-5', 'pl-10', 'pl-14', 'pl-20', 'pl-24'];
$section = static function (string $type, string $label) use ($ids, $map, $opts, $indent, $from, $to) {
    $rows = array_filter($ids, static fn ($id) => $map[$id]['account_type'] === $type);
    $total = 0.0;
    foreach ($map as $r) {
        if (!$r['is_group'] && $r['account_type'] === $type) {
            $total += $type === 'income' ? $r['cr'] - $r['dr'] : $r['dr'] - $r['cr'];
        }
    }
    ob_start(); ?>
    <tr><td colspan="2" class="bg-slate-50 pt-4 text-xs font-bold tracking-wider text-slate-500 uppercase"><?= e($label) ?></td></tr>
    <?php foreach ($rows as $id): $r = $map[$id]; $v = $type === 'income' ? $r['cr'] - $r['dr'] : $r['dr'] - $r['cr']; ?>
      <tr class="<?= $r['is_group'] ? 'font-semibold text-slate-900' : '' ?>">
        <td class="<?= $indent[min($opts['groups'] ? $r['depth'] : 1, 5)] ?>"><?php if (!$r['is_group']): ?><a class="hover:text-cyan-700" href="<?= e(url('/reports/ledger', ['account_id' => $id, 'from' => $from, 'to' => $to])) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
        <td class="text-right tabular-nums <?= $v < 0 ? 'text-rose-600' : '' ?>"><?= money($v) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr class="font-bold text-slate-900"><td class="border-t-2 border-slate-200">Total <?= e(strtolower($label)) ?></td><td class="border-t-2 border-slate-200 text-right tabular-nums"><?= money($total) ?></td></tr>
    <?php return (string) ob_get_clean();
};
$net = $income - $expense;
?>
<div class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-3">
  <div class="rounded-lg bg-indigo-50 px-4 py-3"><div class="text-xs font-semibold text-indigo-700">Income</div><div class="text-xl font-extrabold tabular-nums text-indigo-900"><?= money($income) ?></div></div>
  <div class="rounded-lg bg-rose-50 px-4 py-3"><div class="text-xs font-semibold text-rose-700">Expenses</div><div class="text-xl font-extrabold tabular-nums text-rose-900"><?= money($expense) ?></div></div>
  <div class="rounded-lg <?= $net >= 0 ? 'bg-emerald-50' : 'bg-amber-50' ?> px-4 py-3"><div class="text-xs font-semibold <?= $net >= 0 ? 'text-emerald-700' : 'text-amber-700' ?>">Net <?= $net >= 0 ? 'profit' : 'loss' ?></div><div class="text-xl font-extrabold tabular-nums text-slate-900"><?= money($net) ?></div><?php if ($income > 0): ?><div class="text-xs text-slate-500">Margin <?= number_format($net / $income * 100, 1) ?>%</div><?php endif; ?></div>
</div>
<table class="erp-table">
  <thead><tr><th>Particulars · <?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></th><th class="w-48 text-right">Amount</th></tr></thead>
  <tbody>
    <?= $section('income', 'Income') ?>
    <?= $section('expense', 'Expenses') ?>
  </tbody>
  <tfoot><tr class="bg-slate-100 text-base font-extrabold text-slate-900"><td class="px-3 py-2.5">Net <?= $net >= 0 ? 'Profit' : 'Loss' ?></td><td class="px-3 py-2.5 text-right tabular-nums <?= $net < 0 ? 'text-rose-600' : '' ?>"><?= money($net) ?></td></tr></tfoot>
</table>
