<?php
/** Catalogue with Amazon-style filters on the left. */
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$catName = '';
foreach ($categories as $c) { if ((int) $c['id'] === $f['cat']) { $catName = $c['name']; } }
$active = (int) ($f['q'] !== '') + (int) (bool) $f['cat'] + count($f['brands']) + (int) ($f['min'] !== null || $f['max'] !== null) + (int) (bool) $f['rating'] + (int) $f['inStock'] + (int) $f['offers'] + array_sum(array_map('count', $f['attrSel']));
?>
<div class="flex flex-col gap-5 lg:flex-row">
  <aside class="w-full shrink-0 lg:w-60" aria-label="Filters">
    <form method="get" action="<?= e($s()) ?>" class="space-y-5 text-sm" data-autosubmit id="shop-filters">
      <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?= e($f['sort']) ?>">
      <div>
        <div class="mb-1.5 font-bold text-slate-900">Category</div>
        <select name="category" class="input"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['cat'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['n'] ?>)</option><?php endforeach; ?></select>
      </div>
      <div>
        <div class="mb-1.5 font-bold text-slate-900">Price (<?= e($store['base_currency']) ?>)</div>
        <div class="flex items-center gap-2"><input name="min" type="number" min="0" step="any" value="<?= e($f['min'] ?? '') ?>" class="input" placeholder="<?= e((string) $priceRange[0]) ?>" aria-label="Minimum price"><span class="text-slate-400">–</span><input name="max" type="number" min="0" step="any" value="<?= e($f['max'] ?? '') ?>" class="input" placeholder="<?= e((string) $priceRange[1]) ?>" aria-label="Maximum price"></div>
        <button class="btn btn-sm mt-2 w-full" type="submit">Apply price</button>
      </div>
      <?php if ($brandFacet): ?>
      <div>
        <div class="mb-1.5 font-bold text-slate-900">Brand</div>
        <?php foreach ($brandFacet as $b => $n): ?><label class="flex cursor-pointer items-center gap-2 py-0.5 text-slate-700"><input type="checkbox" class="check" name="brand[]" value="<?= e($b) ?>" <?= in_array($b, $f['brands'], true) ? 'checked' : '' ?>><span class="flex-1"><?= e($b) ?></span><span class="text-xs text-slate-400"><?= (int) $n ?></span></label><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php foreach ($attrFacet as $name => $vals): ksort($vals); ?>
      <div>
        <div class="mb-1.5 font-bold text-slate-900"><?= e($name) ?></div>
        <?php foreach ($vals as $v => $n): ?><label class="flex cursor-pointer items-center gap-2 py-0.5 text-slate-700"><input type="checkbox" class="check" name="attr[<?= e($name) ?>][]" value="<?= e($v) ?>" <?= in_array($v, $f['attrSel'][$name] ?? [], true) ? 'checked' : '' ?>><span class="flex-1"><?= e($v) ?></span><span class="text-xs text-slate-400"><?= (int) $n ?></span></label><?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <div>
        <div class="mb-1.5 font-bold text-slate-900">Customer rating</div>
        <?php foreach ([4, 3, 2] as $r): ?><label class="flex cursor-pointer items-center gap-2 py-0.5"><input type="radio" class="check" name="rating" value="<?= $r ?>" <?= $f['rating'] === $r ? 'checked' : '' ?>><span class="text-amber-500"><?= str_repeat('★', $r) ?><span class="text-slate-300"><?= str_repeat('★', 5 - $r) ?></span></span><span class="text-slate-600">& up</span></label><?php endforeach; ?>
      </div>
      <div class="space-y-1">
        <div class="mb-1.5 font-bold text-slate-900">Availability & offers</div>
        <label class="flex cursor-pointer items-center gap-2 text-slate-700"><input type="checkbox" class="check" name="in_stock" value="1" <?= $f['inStock'] ? 'checked' : '' ?>>In stock only</label>
        <label class="flex cursor-pointer items-center gap-2 text-slate-700"><input type="checkbox" class="check" name="offers" value="1" <?= $f['offers'] ? 'checked' : '' ?>>On offer (discounted)</label>
      </div>
      <?php if ($active): ?><a href="<?= e($s()) ?>" class="btn w-full"><?= icon('rotate-ccw', 'size-4') ?>Clear all filters</a><?php endif; ?>
    </form>
  </aside>

  <section class="min-w-0 flex-1">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-lg font-bold text-slate-900"><?= e($f['q'] !== '' ? 'Results for “' . $f['q'] . '”' : ($catName ?: 'All products')) ?> <span class="text-sm font-normal text-slate-500">· <?= (int) $total ?> item<?= $total === 1 ? '' : 's' ?></span></h1>
      <form method="get" action="<?= e($s()) ?>" data-autosubmit class="flex items-center gap-2 text-sm">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page'], true)) { continue; } foreach ((array) $v as $kk => $vv): if (is_array($vv)) { foreach ($vv as $x): ?><input type="hidden" name="<?= e($k) ?>[<?= e((string) $kk) ?>][]" value="<?= e($x) ?>"><?php endforeach; continue; } ?><input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e($vv) ?>"><?php endforeach; endforeach; ?>
        <label for="sort" class="text-slate-500">Sort by</label>
        <select id="sort" name="sort" class="input w-auto"><?php foreach (['relevance' => 'Featured', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'newest' => 'Newest', 'rating' => 'Customer rating'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      </form>
    </div>
    <?php if (!$items): ?>
      <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-500"><?= icon('search', 'size-8 mx-auto mb-2 text-slate-300') ?>No products match these filters. <a class="font-semibold text-cyan-700" href="<?= e($s()) ?>">Clear filters</a></div>
    <?php else: ?>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($items as $it): include __DIR__ . '/_card.php'; endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
      <nav class="mt-6 flex justify-center gap-1" aria-label="Pages">
        <?php for ($p = 1; $p <= $pages; $p++): $qq = $_GET; $qq['page'] = $p; ?><a href="<?= e($s('?' . http_build_query($qq))) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : '' ?>"><?= $p ?></a><?php endfor; ?>
      </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
