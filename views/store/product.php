<?php
/** Product page. @var array $it @var array $images */
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$taxName = $store['tax_regime'] === 'IN_GST' ? 'GST' : ($store['tax_regime'] === 'UAE_VAT' ? 'VAT' : 'tax');
$fee = (float) $store['delivery_fee'];
$returnDays = (int) ($it['return_days'] ?: $store['return_days']);
$total = array_sum($dist);
?>
<nav class="mb-3 flex flex-wrap items-center gap-1 text-xs text-slate-500"><a href="<?= e($s()) ?>" class="hover:text-cyan-700">Home</a><?php if ($it['category']): ?><?= icon('chevron-right', 'size-3') ?><a class="hover:text-cyan-700" href="<?= e($s('?category=' . $it['category_id'])) ?>"><?= e($it['category']) ?></a><?php endif; ?><?= icon('chevron-right', 'size-3') ?><span class="text-slate-700"><?= e($it['name']) ?></span></nav>
<div class="grid gap-6 lg:grid-cols-[1.1fr_1fr_18rem]">
  <section data-gallery>
    <div class="aspect-square overflow-hidden rounded-xl border border-slate-200 bg-white">
      <?php if ($images): ?><img src="<?= e(upload_url($images[0]['file'])) ?>" alt="<?= e($images[0]['alt'] ?: $it['name']) ?>" class="size-full object-contain" data-gallery-main><?php else: ?><span class="flex size-full items-center justify-center text-slate-300"><?= icon('package', 'size-20') ?></span><?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
    <div class="mt-3 flex gap-2 overflow-x-auto">
      <?php foreach ($images as $i => $im): ?><button type="button" class="size-16 shrink-0 overflow-hidden rounded-lg border-2 <?= $i === 0 ? 'border-cyan-600' : 'border-slate-200' ?> bg-white" data-gallery-thumb="<?= e(upload_url($im['file'])) ?>" aria-label="Image <?= $i + 1 ?>"><img src="<?= e(upload_url($im['thumb'])) ?>" alt="" class="size-full object-cover"></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section>
    <?php if ($it['brand']): ?><div class="text-sm font-semibold text-cyan-700"><?= e($it['brand']) ?></div><?php endif; ?>
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900"><?= e($it['name']) ?></h1>
    <div class="mt-1 flex items-center gap-2 text-sm"><span class="font-mono text-xs text-slate-400">SKU <?= e($it['sku']) ?></span>
      <?php if ($it['reviews']): ?><a href="#reviews" class="text-amber-500"><?= str_repeat('★', (int) round((float) $it['rating'])) ?><span class="text-slate-300"><?= str_repeat('★', 5 - (int) round((float) $it['rating'])) ?></span> <span class="text-cyan-700"><?= (int) $it['reviews'] ?> rating<?= (int) $it['reviews'] === 1 ? '' : 's' ?></span></a><?php endif; ?></div>
    <div class="mt-4 border-y border-slate-200 py-4">
      <?php if ($it['mrp']): ?><div class="text-sm"><span class="font-bold text-rose-600">-<?= (int) round(($it['mrp'] - $it['price']) / $it['mrp'] * 100) ?>%</span> <span class="text-slate-500">M.R.P. <span class="line-through"><?= money($it['mrp']) ?></span></span></div><?php endif; ?>
      <div class="text-3xl font-extrabold text-slate-900"><span class="text-base font-semibold text-slate-500"><?= e($store['base_currency']) ?></span> <?= money($it['price']) ?></div>
      <div class="text-xs text-slate-500">Inclusive of <?= e($taxName) ?><?= (float) $it['tax_rate'] ? ' (' . e(rtrim(rtrim(number_format((float) $it['tax_rate'], 2), '0'), '.')) . '%)' : '' ?><?= $it['unit'] ? ' · per ' . e($it['unit']) : '' ?></div>
    </div>
    <?php if ($it['short_description']): ?><p class="mt-4 text-slate-700"><?= e($it['short_description']) ?></p><?php endif; ?>
    <?php if ($it['attrs']): ?>
      <table class="mt-4 w-full text-sm"><tbody><?php foreach ($it['attrs'] as $k => $v): ?><tr class="border-b border-slate-100"><td class="w-40 py-1.5 font-semibold text-slate-600"><?= e($k) ?></td><td class="py-1.5 text-slate-900"><?= e($v) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <?php $desc = $it['long_description'] ?: $it['description']; if ($desc): ?><div class="mt-5"><h2 class="font-bold text-slate-900">About this item</h2><p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700"><?= e($desc) ?></p></div><?php endif; ?>
  </section>

  <aside class="h-fit rounded-xl border border-slate-200 bg-white p-4 lg:sticky lg:top-32">
    <div class="text-2xl font-extrabold text-slate-900"><span class="text-sm font-semibold text-slate-500"><?= e($store['base_currency']) ?></span> <?= money($it['price']) ?></div>
    <p class="mt-1 text-sm text-slate-600"><?= $fee > 0 ? 'Delivery ' . e($store['base_currency']) . ' ' . money($fee) . ($store['free_delivery_min'] !== null ? ' · FREE above ' . money($store['free_delivery_min']) : '') : 'FREE delivery' ?></p>
    <?php if (!$it['in_stock']): ?>
      <p class="mt-3 text-lg font-bold text-rose-600">Currently unavailable</p>
    <?php else: ?>
      <p class="mt-3 font-bold <?= $it['available'] !== null && $it['available'] <= (float) $store['low_stock_badge'] ? 'text-amber-700' : 'text-emerald-700' ?>"><?= $it['available'] === null ? 'Available' : ($it['available'] <= (float) $store['low_stock_badge'] ? 'Only ' . (float) $it['available'] . ' left in stock' : 'In stock') ?></p>
      <form method="post" action="<?= e($s('cart/add')) ?>" class="mt-3 space-y-2">
        <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
        <label class="flex items-center gap-2 text-sm"><span class="text-slate-600">Qty</span>
          <select name="qty" class="input w-24"><?php $maxQ = (int) min(10, $it['available'] ?? 10, $it['max_order_qty'] ?? 10); for ($i = max(1, (int) $it['min_order_qty']); $i <= max($maxQ, 1); $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
        <button class="btn w-full border-amber-300 bg-amber-300 py-2 font-semibold text-slate-900 hover:bg-amber-400" type="submit"><?= icon('shopping-cart', 'size-4') ?>Add to cart</button>
        <button class="btn w-full border-orange-400 bg-orange-400 py-2 font-semibold text-slate-900 hover:bg-orange-500" type="submit" name="buy_now" value="1">Buy now</button>
      </form>
    <?php endif; ?>
    <ul class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-xs text-slate-600">
      <li class="flex gap-2"><?= icon('banknote', 'size-4 text-emerald-600') ?>Cash on delivery available</li>
      <li class="flex gap-2"><?= icon('undo-2', 'size-4 text-cyan-700') ?><?= $returnDays ?>-day returns</li>
      <li class="flex gap-2"><?= icon('shield-check', 'size-4 text-cyan-700') ?>Sold by <?= e($store['company_name']) ?></li>
    </ul>
  </aside>
</div>

<section id="reviews" class="mt-10 grid gap-6 lg:grid-cols-[18rem_1fr]">
  <div>
    <h2 class="text-lg font-bold text-slate-900">Customer reviews</h2>
    <?php if ($total): ?>
      <div class="mt-1 text-amber-500"><?= str_repeat('★', (int) round((float) $it['rating'])) ?><span class="text-slate-300"><?= str_repeat('★', 5 - (int) round((float) $it['rating'])) ?></span> <span class="text-slate-700"><?= number_format((float) $it['rating'], 1) ?> out of 5</span></div>
      <div class="mt-3 space-y-1"><?php for ($r = 5; $r >= 1; $r--): $pct = $total ? round($dist[$r] / $total * 100) : 0; ?>
        <div class="flex items-center gap-2 text-xs"><span class="w-10 text-cyan-700"><?= $r ?> star</span><div class="h-3 flex-1 overflow-hidden rounded bg-slate-100"><div class="h-full bg-amber-400" data-width="<?= (int) $pct ?>"></div></div><span class="w-8 text-right text-slate-500"><?= (int) $pct ?>%</span></div>
      <?php endfor; ?></div>
    <?php else: ?><p class="mt-1 text-sm text-slate-500">No reviews yet. Only customers who bought this item can review it.</p><?php endif; ?>
  </div>
  <div class="space-y-4">
    <?php foreach ($reviews as $r): ?>
      <article class="border-b border-slate-200 pb-4">
        <div class="flex items-center gap-2 text-sm"><span class="flex size-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600"><?= e(mb_substr($r['name'], 0, 1)) ?></span><span class="font-semibold text-slate-800"><?= e(strtok($r['name'], ' ')) ?></span><span class="badge badge-green">Verified purchase</span></div>
        <div class="mt-1 text-sm"><span class="text-amber-500"><?= str_repeat('★', (int) $r['rating']) ?><span class="text-slate-300"><?= str_repeat('★', 5 - (int) $r['rating']) ?></span></span> <span class="font-bold text-slate-900"><?= e($r['title']) ?></span></div>
        <div class="text-xs text-slate-500"><?= e(fmt_date(substr($r['created_at'], 0, 10))) ?></div>
        <?php if ($r['body']): ?><p class="mt-1 text-sm text-slate-700"><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($related): ?>
<section class="mt-10"><h2 class="mb-3 text-lg font-bold text-slate-900">More from <?= e($it['category']) ?></h2>
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-4"><?php foreach ($related as $it): include __DIR__ . '/_card.php'; endforeach; ?></div></section>
<?php endif; ?>
