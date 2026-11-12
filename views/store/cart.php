<?php
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$cur = $store['base_currency'];
?>
<h1 class="mb-4 text-2xl font-extrabold text-slate-900">Shopping cart</h1>
<?php if (!$view['lines']): ?>
  <div class="rounded-xl border border-slate-200 bg-white p-10 text-center"><?= icon('shopping-cart', 'size-10 mx-auto mb-2 text-slate-300') ?><p class="text-slate-600">Your cart is empty.</p><a href="<?= e($s()) ?>" class="btn btn-primary mt-4">Continue shopping</a></div>
<?php else: ?>
<div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
  <form method="post" action="<?= e($s('cart/update')) ?>" class="rounded-xl border border-slate-200 bg-white" data-autosubmit>
    <?= csrf_field() ?>
    <?php foreach ($view['lines'] as $l): $it = $l['item']; ?>
      <div class="flex gap-4 border-b border-slate-100 p-4">
        <a href="<?= e($s('item/' . $it['url_slug'])) ?>" class="size-24 shrink-0 overflow-hidden rounded-lg bg-slate-100"><?php if ($it['thumb']): ?><img src="<?= e(upload_url($it['thumb'])) ?>" alt="" class="size-full object-cover"><?php endif; ?></a>
        <div class="min-w-0 flex-1">
          <a href="<?= e($s('item/' . $it['url_slug'])) ?>" class="font-medium text-slate-900 hover:text-cyan-700"><?= e($it['name']) ?></a>
          <div class="text-xs text-slate-500"><?= e($cur) ?> <?= money($it['price']) ?> each</div>
          <?php if ($l['issue']): ?><div class="mt-1 text-sm font-semibold text-rose-600"><?= e($l['issue']) ?> - please change the quantity</div><?php elseif ($it['available'] !== null): ?><div class="text-xs text-emerald-700">In stock</div><?php endif; ?>
          <div class="mt-2 flex items-center gap-3">
            <label class="flex items-center gap-1 text-sm">Qty <input type="number" min="0" step="1" name="qty[<?= (int) $it['id'] ?>]" value="<?= e((string) (float) $l['qty']) ?>" class="input w-20"></label>
            <button class="text-sm text-cyan-700 hover:underline" type="submit" name="remove" value="<?= (int) $it['id'] ?>">Delete</button>
          </div>
        </div>
        <div class="text-right font-bold text-slate-900"><?= e($cur) ?> <?= money($l['line_total']) ?></div>
      </div>
    <?php endforeach; ?>
    <div class="flex justify-between p-4"><a href="<?= e($s()) ?>" class="text-sm font-semibold text-cyan-700">← Continue shopping</a><button class="btn btn-sm" type="submit">Update cart</button></div>
  </form>
  <aside class="h-fit rounded-xl border border-slate-200 bg-white p-4">
    <?php include __DIR__ . '/_summary.php'; ?>
    <a href="<?= e($s('checkout')) ?>" class="btn mt-4 w-full border-amber-300 bg-amber-300 py-2 font-semibold text-slate-900 hover:bg-amber-400 <?= $view['valid'] ? '' : 'pointer-events-none opacity-50' ?>">Proceed to checkout</a>
  </aside>
</div>
<?php endif; ?>
