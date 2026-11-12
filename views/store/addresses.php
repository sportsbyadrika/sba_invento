<?php $s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : '')); ?>
<nav class="mb-3 text-xs text-slate-500"><a href="<?= e($s('account')) ?>" class="hover:text-cyan-700">Your account</a> › Addresses</nav>
<h1 class="mb-4 text-2xl font-extrabold text-slate-900">Your addresses</h1>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <?php foreach ($addresses as $a): ?>
    <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
      <div class="flex items-center gap-2"><span class="badge badge-slate"><?= e($a['label']) ?></span><?= $a['is_default'] ? '<span class="badge badge-cyan">Default</span>' : '' ?></div>
      <p class="mt-2 text-slate-700"><b><?= e($a['name']) ?></b><br><?= e($a['phone']) ?><br><?= e($a['line1']) ?><?= $a['line2'] ? '<br>' . e($a['line2']) : '' ?><br><?= e($a['city']) ?><?= $a['region'] ? ', ' . e($regions[$a['region']] ?? $a['region']) : '' ?> <?= e($a['postal_code']) ?></p>
      <form method="post" action="<?= e($s('account/addresses/delete/' . $a['id'])) ?>" class="mt-3" data-confirm-submit="Remove this address?"><?= csrf_field() ?><button class="text-xs font-semibold text-rose-600" type="submit">Remove</button></form>
    </div>
  <?php endforeach; ?>
  <form method="post" action="<?= e($s('account/addresses')) ?>" class="space-y-2 rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm"><?= csrf_field() ?>
    <div class="font-semibold text-slate-900">Add a new address</div>
    <select name="label" class="input"><option>Home</option><option>Office</option><option>Other</option></select>
    <input name="ship_name" class="input" placeholder="Receiver name *" maxlength="120" value="<?= e(old('ship_name', $customer['name'])) ?>"><?= ferr('ship_name') ?>
    <input name="ship_phone" class="input" placeholder="Contact number *" maxlength="30" value="<?= e(old('ship_phone', $customer['phone_code'] . ' ' . $customer['phone'])) ?>"><?= ferr('ship_phone') ?>
    <input name="line1" class="input" placeholder="Building, street, area *" maxlength="200" value="<?= e(old('line1')) ?>"><?= ferr('line1') ?>
    <input name="line2" class="input" placeholder="Flat / landmark" maxlength="200">
    <input name="city" class="input" placeholder="City *" maxlength="80" value="<?= e(old('city')) ?>"><?= ferr('city') ?>
    <?php if ($regions): ?><select name="region" class="input"><option value="">— <?= $store['tax_regime'] === 'IN_GST' ? 'State' : 'Emirate' ?> —</option><?php foreach ($regions as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input name="postal_code" class="input" placeholder="Postal code" maxlength="20">
    <label class="flex items-center gap-2"><input type="checkbox" class="check" name="is_default" value="1">Make default</label>
    <button class="btn btn-primary w-full" type="submit">Save address</button>
  </form>
</div>
