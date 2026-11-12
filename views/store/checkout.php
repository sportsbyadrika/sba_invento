<?php
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$cur = $store['base_currency'];
$old = static fn ($k, $d = '') => old($k, $d);
$total = $view['items_total'] + $view['delivery_fee'];
$codAllowed = $store['cod_enabled'] && ($store['cod_max'] === null || $total + (float) $store['cod_fee'] <= (float) $store['cod_max']);
$codFee = $codAllowed ? (float) $store['cod_fee'] : 0;
$defaultAddr = $addresses[0]['id'] ?? 0;
?>
<h1 class="mb-4 text-2xl font-extrabold text-slate-900">Checkout</h1>
<form method="post" action="<?= e($s('checkout')) ?>" class="grid gap-5 lg:grid-cols-[1fr_20rem]">
  <?= csrf_field() ?>
  <div class="space-y-5">
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900"><span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">1</span>Delivery address</h2>
      <?= ferr('address_id') ?>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <?php foreach ($addresses as $a): ?>
          <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-cyan-600 has-[:checked]:bg-cyan-50/50">
            <input type="radio" class="check mt-1" name="address_id" value="<?= (int) $a['id'] ?>" <?= (int) old('address_id', $defaultAddr) === (int) $a['id'] ? 'checked' : '' ?>>
            <span class="text-sm"><span class="badge badge-slate"><?= e($a['label']) ?></span><?= $a['is_default'] ? ' <span class="badge badge-cyan">Default</span>' : '' ?>
              <span class="mt-1 block font-semibold text-slate-900"><?= e($a['name']) ?> · <?= e($a['phone']) ?></span>
              <span class="block text-slate-600"><?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?>, <?= e($a['city']) ?><?= $a['region'] ? ', ' . e($regions[$a['region']] ?? $a['region']) : '' ?></span></span>
          </label>
        <?php endforeach; ?>
        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-dashed border-slate-300 p-3 has-[:checked]:border-cyan-600 sm:col-span-2">
          <input type="radio" class="check mt-1" name="address_id" value="0" <?= !$addresses || (string) old('address_id') === '0' ? 'checked' : '' ?> data-new-address>
          <span class="flex-1 text-sm"><span class="font-semibold text-slate-900">Deliver to a new address</span>
            <span class="mt-3 grid gap-3 sm:grid-cols-2" data-new-address-fields>
              <span><span class="label">Label</span><select name="label" class="input"><?php foreach (['Home', 'Office', 'Other'] as $l): ?><option <?= old('label') === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></span>
              <span><span class="label">Receiver name *</span><input name="ship_name" class="input" value="<?= e($old('ship_name', $customer['name'])) ?>" maxlength="120"><?= ferr('ship_name') ?></span>
              <span><span class="label">Contact number *</span><input name="ship_phone" class="input" value="<?= e($old('ship_phone', $customer['phone_code'] . ' ' . $customer['phone'])) ?>" maxlength="30"><?= ferr('ship_phone') ?></span>
              <span><span class="label">City *</span><input name="city" class="input" value="<?= e($old('city')) ?>" maxlength="80"><?= ferr('city') ?></span>
              <span class="sm:col-span-2"><span class="label">Building, street, area *</span><input name="line1" class="input" value="<?= e($old('line1')) ?>" maxlength="200"><?= ferr('line1') ?></span>
              <span><span class="label">Flat / landmark</span><input name="line2" class="input" value="<?= e($old('line2')) ?>" maxlength="200"></span>
              <?php if ($regions): ?><span><span class="label"><?= $store['tax_regime'] === 'IN_GST' ? 'State' : 'Emirate' ?></span><select name="region" class="input"><option value="">—</option><?php foreach ($regions as $k => $l): ?><option value="<?= e($k) ?>" <?= (string) old('region', $store['state_code']) === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></span><?php endif; ?>
              <span><span class="label"><?= $store['tax_regime'] === 'IN_GST' ? 'PIN code' : 'Postal code / P.O. Box' ?></span><input name="postal_code" class="input" value="<?= e($old('postal_code')) ?>" maxlength="20"></span>
              <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" class="check" name="is_default" value="1">Save as my default address</label>
            </span></span>
        </label>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900"><span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">2</span>Payment method</h2>
      <?= ferr('payment_method') ?>
      <div class="mt-3 space-y-2">
        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-cyan-600 has-[:checked]:bg-cyan-50/50 <?= $codAllowed ? '' : 'opacity-50' ?>">
          <input type="radio" class="check mt-1" name="payment_method" value="cod" <?= $codAllowed ? 'checked' : 'disabled' ?>>
          <span class="text-sm"><span class="flex items-center gap-2 font-semibold text-slate-900"><?= icon('banknote', 'size-4 text-emerald-600') ?>Cash on Delivery (COD)</span>
            <span class="block text-slate-600">Pay in cash when the order arrives.<?= $codFee > 0 ? ' A COD fee of ' . e($cur) . ' ' . money($codFee) . ' applies.' : '' ?></span>
            <?php if (!$codAllowed): ?><span class="block text-rose-600">Not available for this order<?= $store['cod_max'] !== null ? ' (limit ' . e($cur) . ' ' . money($store['cod_max']) . ')' : '' ?>.</span><?php endif; ?></span>
        </label>
        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 opacity-70" aria-disabled="true">
          <input type="radio" class="check mt-1" name="payment_method" value="epayment" disabled>
          <span class="text-sm"><span class="flex items-center gap-2 font-semibold text-slate-900"><?= icon('credit-card', 'size-4 text-slate-500') ?>Card / UPI / Apple Pay <span class="badge badge-amber">Coming soon</span></span>
            <span class="block text-slate-600">Secure online payment will be available shortly.</span></span>
        </label>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900"><span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">3</span>Review items</h2>
      <div class="mt-3 divide-y divide-slate-100">
        <?php foreach ($view['lines'] as $l): $it = $l['item']; ?>
          <div class="flex items-center gap-3 py-2 text-sm"><span class="size-12 shrink-0 overflow-hidden rounded bg-slate-100"><?php if ($it['thumb']): ?><img src="<?= e(upload_url($it['thumb'])) ?>" alt="" class="size-full object-cover"><?php endif; ?></span>
            <span class="flex-1"><?= e($it['name']) ?> <span class="text-slate-500">× <?= e((string) (float) $l['qty']) ?></span><?php if ($l['issue']): ?><span class="block font-semibold text-rose-600"><?= e($l['issue']) ?></span><?php endif; ?></span>
            <span class="font-semibold"><?= e($cur) ?> <?= money($l['line_total']) ?></span></div>
        <?php endforeach; ?>
      </div>
      <label class="mt-3 block"><span class="label">Delivery instructions (optional)</span><textarea name="note" class="input" rows="2" maxlength="500"><?= e($old('note')) ?></textarea></label>
    </section>
  </div>
  <aside class="h-fit rounded-xl border border-slate-200 bg-white p-4 lg:sticky lg:top-32">
    <button class="btn w-full border-amber-300 bg-amber-300 py-2.5 font-semibold text-slate-900 hover:bg-amber-400" type="submit" <?= $view['valid'] && $codAllowed ? '' : 'disabled' ?>>Place your order</button>
    <p class="mt-2 text-center text-xs text-slate-500">By placing your order you agree to our <a class="text-cyan-700" href="<?= e($s('page/terms')) ?>">terms</a> and <a class="text-cyan-700" href="<?= e($s('page/returns')) ?>">return policy</a>.</p>
    <div class="mt-4 border-t border-slate-100 pt-3"><?php include __DIR__ . '/_summary.php'; ?></div>
  </aside>
</form>
