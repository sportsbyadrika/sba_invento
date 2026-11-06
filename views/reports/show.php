<?php $company = auth()->company(); $asOnly = $report === 'balance-sheet'; ?>
<div data-report data-endpoint="<?= e(url('/reports/' . $report . '/data')) ?>">
  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?>Reports<?= icon('chevron-right', 'size-3') ?><span class="font-semibold text-slate-700"><?= e($title) ?></span></nav>
      <h1 class="mt-1 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= e($title) ?></h1>
      <p class="text-sm text-slate-500" data-report-subtitle><?= e($company['name']) ?> · amounts in <?= e($company['base_currency']) ?></p>
    </div>
    <div class="no-print flex flex-wrap gap-2">
      <button type="button" class="btn" data-print><?= icon('printer', 'size-4') ?>Print</button>
      <button type="button" class="btn" data-report-csv><?= icon('file-spreadsheet', 'size-4 text-emerald-700') ?>Export</button>
      <button type="button" class="btn is-active" data-filter-toggle aria-expanded="true"><?= icon('sliders-horizontal', 'size-4') ?>Options</button>
    </div>
  </div>
  <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
    <section class="card min-w-0 flex-1 overflow-hidden" data-report-body><div class="p-8"><div class="skeleton mb-3 h-4 w-1/3"></div><div class="skeleton mb-2 h-4 w-full"></div><div class="skeleton h-4 w-2/3"></div></div></section>
    <aside class="filter-panel card lg:sticky lg:top-20" data-filter-panel>
      <form data-report-form class="space-y-4 p-4" data-enter-nav>
        <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900"><?= icon('sliders-horizontal', 'size-4 text-cyan-700') ?>Report options</h2>
        <?php if ($report === 'ledger'): ?>
          <div>
            <label class="label" for="account_id">Account</label>
            <select class="input" id="account_id" name="account_id" data-searchable>
              <option value="">— Select ledger —</option>
              <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) ($_GET['account_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if (!$asOnly): ?>
          <div><label class="label" for="from">From</label><input type="date" class="input" id="from" name="from" value="<?= e($from) ?>"></div>
        <?php endif; ?>
        <div><label class="label" for="to"><?= $asOnly ? 'As of' : 'To' ?></label><input type="date" class="input" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="flex flex-wrap gap-1">
          <?php foreach (['month' => 'This month', 'last-month' => 'Last month', 'quarter' => 'Quarter', 'fy' => 'This FY', 'last-fy' => 'Last FY'] as $p => $pl): ?>
            <button type="button" class="btn btn-sm px-1.5 py-0.5 text-[11px]" data-report-preset="<?= $p ?>" data-fy-start="<?= (int) $company['fiscal_year_start'] ?>"><?= e($pl) ?></button>
          <?php endforeach; ?>
        </div>
        <?php if (in_array($report, ['trial-balance', 'profit-loss', 'balance-sheet'], true)): ?>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="groups" value="1" checked>Show groups (hierarchy)</label>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="zero" value="1">Show zero balances</label>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-full"><?= icon('play', 'size-4') ?>Run report</button>
      </form>
    </aside>
  </div>
</div>
