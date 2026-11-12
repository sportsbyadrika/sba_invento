<?php $cur = $store['base_currency']; $cod = isset($codFee) ? (float) $codFee : 0.0; ?>
<h2 class="font-bold text-slate-900">Order summary</h2>
<dl class="mt-3 space-y-1.5 text-sm">
  <div class="flex justify-between"><dt class="text-slate-600">Items (<?= e((string) (float) $view['count']) ?>)</dt><dd><?= e($cur) ?> <?= money($view['items_total']) ?></dd></div>
  <div class="flex justify-between"><dt class="text-slate-600">Delivery</dt><dd><?= $view['delivery_fee'] > 0 ? e($cur) . ' ' . money($view['delivery_fee']) : '<span class="font-semibold text-emerald-700">FREE</span>' ?></dd></div>
  <?php if ($cod > 0): ?><div class="flex justify-between"><dt class="text-slate-600">Cash on delivery fee</dt><dd><?= e($cur) ?> <?= money($cod) ?></dd></div><?php endif; ?>
  <div class="flex justify-between border-t border-slate-200 pt-2 text-lg font-extrabold text-slate-900"><dt>Order total</dt><dd><?= e($cur) ?> <?= money($view['items_total'] + $view['delivery_fee'] + $cod) ?></dd></div>
  <div class="text-xs text-slate-500">Includes <?= $store['tax_regime'] === 'IN_GST' ? 'GST' : ($store['tax_regime'] === 'UAE_VAT' ? 'VAT' : 'tax') ?> of <?= e($cur) ?> <?= money($view['tax']) ?></div>
  <?php if ($store['free_delivery_min'] !== null && $view['delivery_fee'] > 0): ?><div class="rounded-md bg-emerald-50 px-2 py-1 text-xs text-emerald-800">Add <?= e($cur) ?> <?= money((float) $store['free_delivery_min'] - $view['items_total']) ?> more for FREE delivery</div><?php endif; ?>
</dl>
