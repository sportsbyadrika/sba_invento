<?php /** Product images & online preview. */ ?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div>
    <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/inventory/items')) ?>">Items</a><?= icon('chevron-right', 'size-3') ?><?= e($item['sku']) ?></nav>
    <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('image', 'size-6 text-cyan-700') ?><?= e($item['name']) ?></h1>
    <p class="text-sm text-slate-500"><?= $item['publish_online'] ? '<span class="badge badge-green">Published online</span>' : '<span class="badge badge-slate">Not published</span> - tick “Publish on online shop” in the item form' ?></p>
  </div>
  <?php if ($live && $store['enabled']): ?><a class="btn" target="_blank" rel="noopener" href="<?= e(url('/store/' . $store['slug'] . '/item/' . $live['url_slug'])) ?>"><?= icon('external-link', 'size-4') ?>View in store</a><?php endif; ?>
</div>
<div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
  <section class="card p-4">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
      <?php foreach ($images as $im): ?>
        <figure class="overflow-hidden rounded-lg border <?= $im['is_main'] ? 'border-cyan-600 ring-2 ring-cyan-600/30' : 'border-slate-200' ?>">
          <img src="<?= e(upload_url($im['thumb'])) ?>" alt="<?= e($im['alt']) ?>" class="aspect-square w-full object-cover">
          <figcaption class="flex items-center justify-between gap-1 p-2 text-xs">
            <?php if ($im['is_main']): ?><span class="badge badge-cyan">Main</span><?php else: ?>
              <form method="post" action="<?= e(url('/inventory/items/' . $item['id'] . '/images/' . $im['id'] . '/main')) ?>"><?= csrf_field() ?><button class="font-semibold text-cyan-700" type="submit">Make main</button></form><?php endif; ?>
            <form method="post" action="<?= e(url('/inventory/items/' . $item['id'] . '/images/' . $im['id'] . '/delete')) ?>" data-confirm-submit="Delete this image?"><?= csrf_field() ?><button class="font-semibold text-rose-600" type="submit">Delete</button></form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
      <?php if (!$images): ?><p class="col-span-full py-10 text-center text-sm text-slate-500">No images yet.</p><?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('/inventory/items/' . $item['id'] . '/images')) ?>" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4">
      <?= csrf_field() ?>
      <div><label class="label" for="images">Add images (JPEG, PNG or WebP, max 8 MB each, up to 12)</label><input class="input" id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
      <div class="min-w-48 flex-1"><label class="label" for="alt">Description (for search engines & screen readers)</label><input class="input" id="alt" name="alt" value="<?= e($item['name']) ?>" maxlength="150"></div>
      <button class="btn btn-primary" type="submit"><?= icon('upload', 'size-4') ?>Upload</button>
    </form>
  </section>
  <aside class="card p-4 text-sm">
    <h2 class="font-bold text-slate-900">Online preview</h2>
    <?php if ($live): ?>
      <dl class="mt-2 space-y-1.5">
        <div class="flex justify-between"><dt class="text-slate-500">Shop price (incl. tax)</dt><dd class="font-bold"><?= money($live['price']) ?></dd></div>
        <?php if ($live['mrp']): ?><div class="flex justify-between"><dt class="text-slate-500">MRP / was</dt><dd class="line-through"><?= money($live['mrp']) ?></dd></div><?php endif; ?>
        <div class="flex justify-between"><dt class="text-slate-500">Shown as available</dt><dd><?= $live['available'] === null ? 'Unlimited (service)' : e((string) (float) $live['available']) ?></dd></div>
        <?php if ($item['online_stock_cap'] !== null): ?><div class="flex justify-between"><dt class="text-slate-500">Online cap</dt><dd><?= e((string) (float) $item['online_stock_cap']) ?></dd></div><?php endif; ?>
        <div class="flex justify-between"><dt class="text-slate-500">Filters</dt><dd class="text-right"><?= $live['attrs'] ? e(implode(', ', array_map(static fn ($k, $v) => "$k: $v", array_keys($live['attrs']), $live['attrs']))) : '—' ?></dd></div>
      </dl>
    <?php else: ?><p class="mt-2 text-slate-500">Publish the item (and keep it active) to see how it appears online.</p><?php endif; ?>
  </aside>
</div>
