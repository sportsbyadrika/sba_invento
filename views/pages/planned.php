<?php
$phases = App\Controllers\PlannedController::PHASES;
$details = [
    'Invoices' => ['Customer, place of supply & currency picked first; tax (VAT / CGST+SGST / IGST) calculated per line', 'Enter-key line entry like a voucher; item search by SKU / barcode', 'Posts to receivables, sales, output tax and COGS through the journal engine', 'UAE e-invoicing (PINT-AE) and India IRN / e-way bill hooks'],
    'Bills' => ['Vendor bills with input-tax and reverse-charge handling', 'Match against purchase order and GRN (3-way match)', 'Posts to payables, purchase/expense and input tax'],
    'VAT Return (UAE)' => ['VAT 201 box-wise summary per emirate', 'Standard, zero-rated, exempt and reverse-charge totals', 'Export for EmaraTax filing'],
    'GSTR-1 / GSTR-3B (India)' => ['B2B / B2C / CDNR / HSN summary', 'Offline JSON for the GST portal', 'Reconciliation with GSTR-2B'],
];
?>
<div class="mx-auto max-w-3xl py-8">
  <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?><?= e($item['group']) ?><?= icon('chevron-right', 'size-3') ?><span class="font-semibold text-slate-700"><?= e($item['label']) ?></span></nav>
  <div class="card mt-3 p-6 sm:p-8">
    <div class="flex items-start gap-4">
      <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700"><?= icon($item['icon'] ?? 'clock', 'size-6') ?></span>
      <div>
        <span class="badge badge-amber">Planned · Phase <?= (int) $item['planned'] ?> — <?= e($phaseName) ?></span>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900"><?= e($item['label']) ?></h1>
        <p class="mt-1 text-slate-600">This screen is designed and scheduled. It will use the same list, filter panel and keyboard-first entry you already see in Chart of Accounts and Journals, and will post into the general ledger through the journal engine.</p>
      </div>
    </div>
    <?php if (isset($details[$item['label']])): ?>
      <ul class="mt-6 space-y-2 text-sm text-slate-700">
        <?php foreach ($details[$item['label']] as $d): ?><li class="flex gap-2"><?= icon('circle-check', 'size-4 mt-0.5 shrink-0 text-emerald-600') ?><?= e($d) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div class="mt-8">
      <h2 class="section-title mb-3">Delivery roadmap</h2>
      <ol class="grid gap-2 sm:grid-cols-2">
        <?php foreach ($phases as $n => $p): ?>
          <li class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm <?= $n === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($n === (int) $item['planned'] ? 'border-cyan-300 bg-cyan-50 text-cyan-900' : 'border-slate-200 text-slate-600') ?>">
            <span class="font-mono text-xs font-bold"><?= $n ?></span><?= e($p) ?><?= $n === 1 ? icon('check', 'size-4 ml-auto') : '' ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</div>
