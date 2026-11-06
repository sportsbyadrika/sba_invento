<?php
$company = auth()->company();
$user = auth()->user();
$hour = (int) (new DateTime('now', new DateTimeZone(display_tz())))->format('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$tones = ['cyan' => 'bg-cyan-50 text-cyan-700', 'emerald' => 'bg-emerald-50 text-emerald-700', 'amber' => 'bg-amber-50 text-amber-700', 'indigo' => 'bg-indigo-50 text-indigo-700'];
// chart scale (6 months income vs expense)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
    $months[$ym] = ['income' => 0.0, 'expense' => 0.0];
}
foreach ($monthly as $m) {
    if (isset($months[$m['ym']])) {
        $months[$m['ym']] = ['income' => (float) $m['income'], 'expense' => (float) $m['expense']];
    }
}
$max = max(1, ...array_map(static fn ($m) => max($m['income'], $m['expense']), array_values($months)));
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div>
    <p class="text-sm text-slate-500"><?= e($greet) ?>, <?= e(strtok((string) $user['name'], ' ')) ?></p>
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900"><?= e($company['name'] ?? 'Platform overview') ?></h1>
  </div>
  <?php if ($company): ?>
  <div class="flex flex-wrap gap-2">
    <?php if (can('accountant', 'create')): ?><a href="<?= e(url('/accountant/journals/new')) ?>" class="btn btn-primary"><?= icon('plus', 'size-4') ?>New Journal</a><?php endif; ?>
    <a href="<?= e(url('/reports/trial-balance')) ?>" class="btn"><?= icon('scale', 'size-4') ?>Trial Balance</a>
  </div>
  <?php endif; ?>
</div>

<?php if (!$company): ?>
  <div class="card p-8 text-center">
    <p class="font-semibold text-slate-800">You are signed in as platform super admin.</p>
    <p class="mt-1 text-sm text-slate-500">Create the first company and its administrator to start.</p>
    <a href="<?= e(url('/admin/companies')) ?>" class="btn btn-primary mt-4"><?= icon('building-2', 'size-4') ?>Manage companies</a>
  </div>
<?php else: ?>
<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
  <?php foreach ($kpi as $k): ?>
    <div class="card p-4">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-500"><?= e($k['label']) ?></span>
        <span class="flex size-8 items-center justify-center rounded-lg <?= $tones[$k['tone']] ?>"><?= icon($k['icon'], 'size-4') ?></span>
      </div>
      <div class="mt-2 text-xl font-extrabold tabular-nums text-slate-900 sm:text-2xl <?= $k['value'] < 0 ? 'text-rose-600' : '' ?>"><span class="mr-1 text-xs font-semibold text-slate-400"><?= e($company['base_currency']) ?></span><?= money($k['value']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-3">
  <section class="card p-4 xl:col-span-2">
    <div class="mb-3 flex items-center justify-between">
      <h2 class="font-bold text-slate-900">Income vs Expense <span class="text-sm font-normal text-slate-500">· last 6 months (posted)</span></h2>
      <div class="flex gap-3 text-xs text-slate-500"><span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-cyan-600"></span>Income</span><span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-amber-400"></span>Expense</span></div>
    </div>
    <svg viewBox="0 0 600 220" class="h-56 w-full" role="img" aria-label="Income and expense by month">
      <?php foreach ([0, 0.25, 0.5, 0.75, 1] as $g): $y = 190 - $g * 170; ?>
        <line x1="40" x2="590" y1="<?= $y ?>" y2="<?= $y ?>" stroke="#e2e8f0" stroke-width="1"/>
        <text x="34" y="<?= $y + 3 ?>" text-anchor="end" font-size="9" fill="#94a3b8"><?= e(number_format($max * $g / 1000, $max >= 10000 ? 0 : 1)) ?>k</text>
      <?php endforeach; ?>
      <?php $i = 0; foreach ($months as $ym => $m): $x = 55 + $i * 90; $hi = $m['income'] / $max * 170; $he = $m['expense'] / $max * 170; ?>
        <rect x="<?= $x ?>" y="<?= 190 - $hi ?>" width="26" height="<?= max(0, $hi) ?>" rx="3" fill="#0891b2"><title>Income <?= e(money($m['income'])) ?></title></rect>
        <rect x="<?= $x + 30 ?>" y="<?= 190 - $he ?>" width="26" height="<?= max(0, $he) ?>" rx="3" fill="#fbbf24"><title>Expense <?= e(money($m['expense'])) ?></title></rect>
        <text x="<?= $x + 28 ?>" y="207" text-anchor="middle" font-size="10" fill="#64748b"><?= e(date('M y', strtotime($ym . '-01'))) ?></text>
      <?php $i++; endforeach; ?>
    </svg>
  </section>

  <section class="card p-4">
    <h2 class="mb-3 font-bold text-slate-900">At a glance</h2>
    <dl class="divide-y divide-slate-100 text-sm">
      <?php foreach ([['Accounts in COA', $counts['accounts'], '/accountant/chart-of-accounts'], ['Draft journals', $counts['drafts'], '/accountant/journals'], ['Active items', $counts['items'], '/inventory/items'], ['Customers', $counts['customers'], '/sales/customers'], ['Vendors', $counts['vendors'], '/purchases/vendors']] as [$l, $v, $u]): ?>
        <a href="<?= e(url($u)) ?>" class="flex items-center justify-between py-2 hover:text-cyan-700"><dt class="text-slate-600"><?= e($l) ?></dt><dd class="font-bold tabular-nums text-slate-900"><?= number_format((int) $v) ?></dd></a>
      <?php endforeach; ?>
    </dl>
  </section>
</div>

<section class="card mt-4 overflow-hidden">
  <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
    <h2 class="font-bold text-slate-900">Recent journals</h2>
    <a href="<?= e(url('/accountant/journals')) ?>" class="text-sm font-semibold text-cyan-700 hover:underline">View all</a>
  </div>
  <div class="overflow-x-auto">
  <table class="erp-table">
    <thead><tr><th>Date</th><th>Journal #</th><th>Narration</th><th class="text-right">Amount (<?= e($company['base_currency']) ?>)</th><th>Status</th></tr></thead>
    <tbody>
      <?php if (!$recent): ?><tr><td colspan="5" class="py-8 text-center text-slate-500">No journals yet. <a class="font-semibold text-cyan-700" href="<?= e(url('/accountant/journals/new')) ?>">Create the first one</a>.</td></tr><?php endif; ?>
      <?php foreach ($recent as $j): ?>
        <tr class="cursor-pointer" data-href="<?= e(url('/accountant/journals/' . $j['id'])) ?>">
          <td><?= e(fmt_date($j['journal_date'])) ?></td>
          <td class="font-mono text-xs font-semibold"><?= e($j['number']) ?></td>
          <td class="max-w-md truncate"><?= e($j['narration']) ?></td>
          <td class="text-right tabular-nums"><?= money($j['total_base']) ?></td>
          <td><?= App\Core\Resource::badge(['draft' => ['Draft', 'amber'], 'posted' => ['Posted', 'green'], 'void' => ['Void', 'slate']], $j['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>
