<?php $url = rtrim((string) config('app.url'), '/') . url('/store/' . $s['slug']); ?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('store', 'size-6 text-cyan-700') ?>Shop Settings</h1>
    <p class="text-sm text-slate-500"><?= (int) $published ?> item(s) published · publish items from Inventory → Items (Online shop section).</p>
  </div>
  <?php if ($s['enabled']): ?><a class="btn" href="<?= e(url('/store/' . $s['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external-link', 'size-4') ?>Open store</a><?php endif; ?>
</div>
<form method="post" action="<?= e(url('/shop/settings')) ?>" class="card max-w-4xl p-5" data-ajax-form data-enter-nav novalidate>
  <?= csrf_field() ?>
  <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
  <div class="grid gap-4 sm:grid-cols-2">
    <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2"><input type="checkbox" class="check" name="enabled" value="1" <?= $s['enabled'] ? 'checked' : '' ?>>Store is open to the public</label>
    <div><label class="label" for="store_name">Store name</label><input class="input" id="store_name" name="store_name" value="<?= e($s['store_name']) ?>" maxlength="120"><p class="field-error hidden" data-error-for="store_name"></p></div>
    <div><label class="label" for="slug">Web address</label><div class="flex items-center gap-1"><span class="text-sm text-slate-500">/store/</span><input class="input font-mono" id="slug" name="slug" value="<?= e($s['slug']) ?>" maxlength="60"></div><p class="help"><?= e($url) ?></p><p class="field-error hidden" data-error-for="slug"></p></div>
    <div class="sm:col-span-2"><label class="label" for="tagline">Tagline</label><input class="input" id="tagline" name="tagline" value="<?= e($s['tagline']) ?>" maxlength="200"></div>
    <div><label class="label" for="contact_email">Support e-mail</label><input class="input" id="contact_email" name="contact_email" value="<?= e($s['contact_email']) ?>"></div>
    <div><label class="label" for="contact_phone">Support phone</label><input class="input" id="contact_phone" name="contact_phone" value="<?= e($s['contact_phone']) ?>"></div>
    <div class="pt-2 sm:col-span-2"><div class="section-title border-b border-slate-100 pb-1.5">Delivery</div></div>
    <div><label class="label" for="delivery_fee">Delivery charge per order</label><input class="input text-right" id="delivery_fee" name="delivery_fee" value="<?= e((string) (float) $s['delivery_fee']) ?>" inputmode="decimal"><p class="field-error hidden" data-error-for="delivery_fee"></p></div>
    <div><label class="label" for="free_delivery_min">Free delivery for orders above</label><input class="input text-right" id="free_delivery_min" name="free_delivery_min" value="<?= e($s['free_delivery_min'] === null ? '' : (string) (float) $s['free_delivery_min']) ?>" placeholder="Leave empty = never free" inputmode="decimal"></div>
    <div class="pt-2 sm:col-span-2"><div class="section-title border-b border-slate-100 pb-1.5">Payment</div></div>
    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" class="check" name="cod_enabled" value="1" <?= $s['cod_enabled'] ? 'checked' : '' ?>>Accept Cash on Delivery</label>
    <div><label class="label" for="cod_max">COD allowed up to (order total)</label><input class="input text-right" id="cod_max" name="cod_max" value="<?= e($s['cod_max'] === null ? '' : (string) (float) $s['cod_max']) ?>" placeholder="No limit" inputmode="decimal"></div>
    <div><label class="label" for="cod_fee">COD fee</label><input class="input text-right" id="cod_fee" name="cod_fee" value="<?= e((string) (float) $s['cod_fee']) ?>" inputmode="decimal"></div>
    <div class="flex items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm text-slate-600 sm:col-span-2"><?= icon('credit-card', 'size-4') ?>Online payment (cards / UPI / Apple Pay) <span class="badge badge-amber">Coming soon</span></div>
    <div class="pt-2 sm:col-span-2"><div class="section-title border-b border-slate-100 pb-1.5">Returns & display</div></div>
    <div><label class="label" for="return_days">Return window (days after delivery)</label><input class="input text-right" id="return_days" name="return_days" value="<?= (int) $s['return_days'] ?>" inputmode="numeric"><p class="help">Items can override this.</p></div>
    <div><label class="label" for="low_stock_badge">Show "Only X left" at or below</label><input class="input text-right" id="low_stock_badge" name="low_stock_badge" value="<?= (int) $s['low_stock_badge'] ?>" inputmode="numeric"></div>
    <div class="pt-2 sm:col-span-2"><div class="section-title border-b border-slate-100 pb-1.5">Policy pages (required by payment gateways)</div></div>
    <?php foreach (['shipping_policy' => 'Shipping & delivery', 'returns_policy' => 'Returns & refunds', 'terms' => 'Terms & conditions', 'privacy' => 'Privacy policy'] as $k => $l): ?>
      <div class="sm:col-span-2"><label class="label" for="<?= $k ?>"><?= $l ?></label><textarea class="input" id="<?= $k ?>" name="<?= $k ?>" rows="4"><?= e($s[$k]) ?></textarea></div>
    <?php endforeach; ?>
  </div>
  <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?>Save settings</button></div>
</form>
