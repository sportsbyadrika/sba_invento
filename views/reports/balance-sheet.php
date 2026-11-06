<?php
$indent = ['', 'pl-5', 'pl-10', 'pl-14', 'pl-20', 'pl-24'];
$side = static function (array $types, string $title, float $extra = 0, string $extraLabel = '') use ($ids, $map, $opts, $indent, $to) {
    ob_start(); $total = 0.0; ?>
    <table class="erp-table">
      <thead><tr><th><?= e($title) ?></th><th class="w-44 text-right">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($ids as $id): $r = $map[$id]; if (!in_array($r['account_type'], $types, true)) { continue; }
          $v = $r['account_type'] === 'asset' ? $r['cb'] : -$r['cb'];
          if (!$r['is_group']) { $total += $v; } ?>
        <tr class="<?= $r['is_group'] ? 'font-semibold text-slate-900' : '' ?>">
          <td class="<?= $indent[min($opts['groups'] ? $r['depth'] : 1, 5)] ?>"><?php if (!$r['is_group']): ?><a class="hover:text-cyan-700" href="<?= e(url('/reports/ledger', ['account_id' => $id, 'to' => $to])) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
          <td class="text-right tabular-nums <?= $v < 0 ? 'text-rose-600' : '' ?>"><?= money($v) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($extraLabel !== ''): $total += $extra; ?>
        <tr class="font-semibold text-slate-900"><td>Profit & Loss A/c <span class="text-xs font-normal text-slate-500">(<?= e($extraLabel) ?>)</span></td><td class="text-right tabular-nums <?= $extra < 0 ? 'text-rose-600' : '' ?>"><?= money($extra) ?></td></tr>
      <?php endif; ?>
      </tbody>
      <tfoot><tr class="bg-slate-100 font-extrabold text-slate-900"><td class="px-3 py-2">Total</td><td class="px-3 py-2 text-right tabular-nums"><?= money($total) ?></td></tr></tfoot>
    </table>
    <?php return [(string) ob_get_clean(), $total];
};
[$left, $lt] = $side(['liability', 'equity'], 'Liabilities & Equity', $profit, 'unappropriated profit to date');
[$right, $rt] = $side(['asset'], 'Assets');
$diff = round($lt - $rt, 2);
?>
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-2 text-sm">
  <span class="text-slate-600">As of <span class="font-semibold text-slate-900"><?= e(fmt_date($to)) ?></span></span>
  <?php if (abs($diff) < 0.01): ?><span class="badge badge-green">Assets = Liabilities + Equity</span><?php else: ?><span class="badge badge-rose">Difference <?= money($diff) ?> — usually unbalanced opening balances</span><?php endif; ?>
</div>
<div class="grid lg:grid-cols-2 lg:divide-x lg:divide-slate-200">
  <div><?= $left ?></div>
  <div><?= $right ?></div>
</div>
