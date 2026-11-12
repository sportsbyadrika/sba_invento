<?php /** Product card. @var array $it @var callable $s */ ?>
<article class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:shadow-lg">
  <a href="<?= e($s('item/' . $it['url_slug'])) ?>" class="relative block aspect-square bg-slate-100">
    <?php if ($it['thumb']): ?><img src="<?= e(upload_url($it['thumb'])) ?>" alt="<?= e($it['name']) ?>" loading="lazy" class="size-full object-cover transition group-hover:scale-[1.02]"><?php else: ?><span class="flex size-full items-center justify-center text-slate-300"><?= icon('package', 'size-12') ?></span><?php endif; ?>
    <?php if ($it['mrp']): ?><span class="absolute top-2 left-2 rounded-md bg-rose-600 px-1.5 py-0.5 text-[11px] font-bold text-white"><?= (int) round(($it['mrp'] - $it['price']) / $it['mrp'] * 100) ?>% off</span><?php endif; ?>
    <?php if (!$it['in_stock']): ?><span class="absolute inset-x-0 bottom-0 bg-slate-900/70 py-1 text-center text-xs font-semibold text-white">Out of stock</span><?php endif; ?>
  </a>
  <div class="flex flex-1 flex-col p-3">
    <?php if ($it['brand']): ?><div class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase"><?= e($it['brand']) ?></div><?php endif; ?>
    <a href="<?= e($s('item/' . $it['url_slug'])) ?>" class="line-clamp-2 text-sm font-medium text-slate-900 hover:text-cyan-700"><?= e($it['name']) ?></a>
    <?php if ($it['reviews']): ?><div class="mt-1 flex items-center gap-1 text-xs text-amber-500"><?= str_repeat('★', (int) round((float) $it['rating'])) ?><span class="text-slate-300"><?= str_repeat('★', 5 - (int) round((float) $it['rating'])) ?></span><span class="text-slate-500">(<?= (int) $it['reviews'] ?>)</span></div><?php endif; ?>
    <div class="mt-auto pt-2">
      <span class="text-lg font-extrabold text-slate-900"><span class="text-xs font-semibold text-slate-500"><?= e($store['base_currency']) ?></span> <?= money($it['price']) ?></span>
      <?php if ($it['mrp']): ?><span class="ml-1 text-xs text-slate-400 line-through"><?= money($it['mrp']) ?></span><?php endif; ?>
      <?php if ($it['in_stock'] && $it['available'] !== null && $it['available'] <= (float) $store['low_stock_badge']): ?><div class="text-xs font-semibold text-amber-700">Only <?= (float) $it['available'] ?> left</div><?php endif; ?>
    </div>
    <?php if ($it['in_stock']): ?>
    <form method="post" action="<?= e($s('cart/add')) ?>" class="mt-2"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
      <button class="btn btn-sm w-full border-amber-300 bg-amber-300 font-semibold text-slate-900 hover:bg-amber-400" type="submit"><?= icon('shopping-cart', 'size-3.5') ?>Add to cart</button></form>
    <?php endif; ?>
  </div>
</article>
