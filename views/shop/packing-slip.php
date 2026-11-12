<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Packing slip <?= e($o['number']) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><script src="<?= e(asset('js/app.js')) ?>" defer></script></head>
<body class="bg-slate-100 print:bg-white">
<div class="no-print flex justify-center gap-2 border-b border-slate-200 bg-white p-2"><button type="button" class="btn btn-primary" data-print>Print</button><button type="button" class="btn" data-history-back>Close</button></div>
<main class="mx-auto my-6 max-w-[210mm] bg-white p-10 text-sm shadow print:my-0 print:p-0 print:shadow-none">
  <div class="flex justify-between border-b-2 border-slate-900 pb-4">
    <div><div class="text-xl font-extrabold"><?= e($store['store_name']) ?></div><div class="text-slate-600"><?= e($company['name']) ?> · <?= e($store['contact_phone']) ?></div></div>
    <div class="text-right"><div class="text-lg font-extrabold uppercase">Packing slip</div><div class="font-mono text-lg font-bold"><?= e($o['number']) ?></div><div class="text-slate-600"><?= e(local_dt($o['placed_at'], 'd-M-Y')) ?></div></div>
  </div>
  <div class="mt-4 grid grid-cols-2 gap-6">
    <div><div class="text-xs font-bold text-slate-500 uppercase">Ship to</div><div class="mt-1 text-base font-bold"><?= e($o['ship_name']) ?></div><div><?= e($o['ship_phone']) ?></div><div class="whitespace-pre-line"><?= e($o['ship_address']) ?></div><div class="font-semibold"><?= e($o['ship_city']) ?> <?= e($o['ship_region']) ?></div></div>
    <div><div class="text-xs font-bold text-slate-500 uppercase">Payment</div><div class="mt-1 text-base font-bold"><?= $o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid' ? 'CASH ON DELIVERY: ' . e($o['currency_code']) . ' ' . money($o['total']) : 'PAID' ?></div><?php if ($o['customer_note']): ?><div class="mt-2 rounded border border-slate-300 p-2">Note: <?= e($o['customer_note']) ?></div><?php endif; ?></div>
  </div>
  <table class="mt-6 w-full border-collapse"><thead><tr class="border-y border-slate-300 bg-slate-50 text-left text-xs uppercase"><th class="px-2 py-2">✓</th><th class="px-2 py-2">SKU</th><th class="px-2 py-2">Item</th><th class="px-2 py-2 text-right">Qty</th></tr></thead><tbody>
    <?php foreach ($o['lines'] as $l): ?><tr class="border-b border-slate-200"><td class="px-2 py-2"><span class="inline-block size-4 border border-slate-500"></span></td><td class="px-2 py-2 font-mono"><?= e($l['sku']) ?></td><td class="px-2 py-2"><?= e($l['name']) ?></td><td class="px-2 py-2 text-right text-base font-bold"><?= e((string) (float) $l['qty']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <div class="mt-10 grid grid-cols-2 gap-10 text-xs text-slate-500"><div class="border-t border-slate-400 pt-1">Packed by</div><div class="border-t border-slate-400 pt-1">Received by (name & signature)</div></div>
</main></body></html>
