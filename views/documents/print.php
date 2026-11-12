<?php
/** Printable document (A4). Used by staff and by shop customers (their own invoices). */
$isSale = $cfg['contact'] === 'customer';
$title = ['invoice' => ($company['tax_regime'] === 'NONE' ? 'Invoice' : 'Tax Invoice'), 'quote' => 'Quotation', 'credit_note' => ($company['tax_regime'] === 'NONE' ? 'Credit Note' : 'Tax Credit Note'), 'po' => 'Purchase Order'][$doc['doc_type']];
$taxLabel = ['UAE_VAT' => 'TRN', 'IN_GST' => 'GSTIN'][$company['tax_regime']] ?? 'Tax No.';
$q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title . ' ' . $doc['number']) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="bg-slate-100 print:bg-white">
<div class="no-print sticky top-0 flex justify-center gap-2 border-b border-slate-200 bg-white/90 p-2 backdrop-blur">
  <button type="button" class="btn btn-primary" data-print><?= icon('printer', 'size-4') ?>Print / Save as PDF</button>
  <button type="button" class="btn" data-history-back>Close</button>
</div>
<main class="mx-auto my-6 max-w-[210mm] bg-white p-10 text-sm text-slate-800 shadow print:my-0 print:p-0 print:shadow-none">
  <header class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-5">
    <div>
      <div class="text-2xl font-extrabold text-slate-900"><?= e($company['legal_name'] ?: $company['name']) ?></div>
      <div class="mt-1 whitespace-pre-line text-slate-600"><?= e($company['address']) ?></div>
      <div class="mt-1 text-slate-600"><?= e(implode(' · ', array_filter([$company['phone'], $company['email']]))) ?></div>
      <?php if ($company['tax_number']): ?><div class="mt-1 font-semibold"><?= e($taxLabel) ?>: <?= e($company['tax_number']) ?></div><?php endif; ?>
    </div>
    <div class="text-right">
      <div class="text-xl font-extrabold tracking-wide text-slate-900 uppercase"><?= e($title) ?></div>
      <table class="mt-2 ml-auto text-sm"><tbody>
        <tr><td class="pr-3 text-slate-500">Number</td><td class="font-mono font-semibold"><?= e($doc['number']) ?></td></tr>
        <tr><td class="pr-3 text-slate-500">Date</td><td><?= e(fmt_date($doc['doc_date'])) ?></td></tr>
        <?php if ($doc['due_date']): ?><tr><td class="pr-3 text-slate-500"><?= e($cfg['due']) ?></td><td><?= e(fmt_date($doc['due_date'])) ?></td></tr><?php endif; ?>
        <?php if ($doc['reference']): ?><tr><td class="pr-3 text-slate-500">Reference</td><td><?= e($doc['reference']) ?></td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </header>
  <section class="mt-5 grid grid-cols-2 gap-6">
    <div>
      <div class="text-xs font-bold tracking-wider text-slate-500 uppercase"><?= $isSale ? 'Bill to' : 'Vendor' ?></div>
      <div class="mt-1 font-semibold text-slate-900"><?= e($doc['contact_name']) ?></div>
      <div class="whitespace-pre-line text-slate-600"><?= e($doc['billing_address'] ?: $doc['contact_address']) ?></div>
      <?php if ($doc['contact_tax_number']): ?><div class="text-slate-600"><?= e($taxLabel) ?>: <?= e($doc['contact_tax_number']) ?></div><?php endif; ?>
    </div>
    <?php if ($doc['shipping_address']): ?><div><div class="text-xs font-bold tracking-wider text-slate-500 uppercase">Ship to</div><div class="mt-1 whitespace-pre-line text-slate-600"><?= e($doc['shipping_address']) ?></div></div><?php endif; ?>
  </section>
  <table class="mt-6 w-full border-collapse text-sm">
    <thead><tr class="border-y border-slate-300 bg-slate-50 text-left text-xs text-slate-600 uppercase"><th class="px-2 py-2">#</th><th class="px-2 py-2">Description</th><th class="px-2 py-2 text-right">Qty</th><th class="px-2 py-2 text-right">Rate</th><th class="px-2 py-2 text-right">Disc</th><th class="px-2 py-2 text-right">Tax</th><th class="px-2 py-2 text-right">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($doc['lines'] as $l): ?>
      <tr class="border-b border-slate-200 align-top">
        <td class="px-2 py-2 text-slate-500"><?= (int) $l['line_no'] ?></td>
        <td class="px-2 py-2"><span class="font-medium text-slate-900"><?= e($l['item_name'] ?: $l['description']) ?></span><?php if ($l['item_name'] && $l['description']): ?><span class="block text-xs text-slate-500"><?= e($l['description']) ?></span><?php endif; ?><?php if ($l['sku']): ?><span class="block font-mono text-[11px] text-slate-400"><?= e($l['sku']) ?></span><?php endif; ?></td>
        <td class="px-2 py-2 text-right tabular-nums"><?= e($q($l['qty'])) ?> <?= e($l['unit']) ?></td>
        <td class="px-2 py-2 text-right tabular-nums"><?= money($l['unit_price']) ?></td>
        <td class="px-2 py-2 text-right tabular-nums"><?= (float) $l['discount_pct'] ? e($q($l['discount_pct'])) . '%' : '' ?></td>
        <td class="px-2 py-2 text-right tabular-nums"><?= (float) $l['tax_pct'] ? e($q($l['tax_pct'])) . '%' : '' ?></td>
        <td class="px-2 py-2 text-right tabular-nums"><?= money($l['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="mt-4 flex justify-end">
    <table class="w-72 text-sm"><tbody>
      <tr><td class="py-1 text-slate-500">Sub total</td><td class="py-1 text-right tabular-nums"><?= money($doc['subtotal']) ?></td></tr>
      <?php foreach ($taxSummary as [$lbl, $pct, $amt]): ?><tr><td class="py-1 text-slate-500"><?= e($lbl) ?> @ <?= e($q($pct)) ?>%</td><td class="py-1 text-right tabular-nums"><?= money($amt) ?></td></tr><?php endforeach; ?>
      <?php if ((float) $doc['freight']): ?><tr><td class="py-1 text-slate-500"><?= $isSale ? 'Delivery charge' : 'Freight' ?></td><td class="py-1 text-right tabular-nums"><?= money($doc['freight']) ?></td></tr><?php endif; ?>
      <tr class="border-t-2 border-slate-900 text-base font-extrabold"><td class="py-2">Total <?= e($doc['currency_code']) ?></td><td class="py-2 text-right tabular-nums"><?= money($doc['total']) ?></td></tr>
      <?php if ((float) $doc['amount_paid'] > 0): ?><tr><td class="py-1 text-slate-500">Paid</td><td class="py-1 text-right tabular-nums"><?= money($doc['amount_paid']) ?></td></tr><tr class="font-bold"><td class="py-1">Balance</td><td class="py-1 text-right tabular-nums"><?= money((float) $doc['total'] - (float) $doc['amount_paid']) ?></td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <?php if ($doc['notes']): ?><div class="mt-6"><div class="text-xs font-bold tracking-wider text-slate-500 uppercase">Notes</div><p class="mt-1 whitespace-pre-line"><?= e($doc['notes']) ?></p></div><?php endif; ?>
  <?php if ($doc['terms']): ?><div class="mt-4"><div class="text-xs font-bold tracking-wider text-slate-500 uppercase">Terms</div><p class="mt-1 whitespace-pre-line text-slate-600"><?= e($doc['terms']) ?></p></div><?php endif; ?>
  <footer class="mt-10 border-t border-slate-200 pt-3 text-center text-xs text-slate-400">This is a computer-generated document.</footer>
</main>
</body></html>
