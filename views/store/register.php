<?php
use App\Services\Shop;
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$defCode = ['IN' => '+91', 'AE' => '+971'][$store['country_code']] ?? '+971';
?>
<div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6">
  <h1 class="text-2xl font-extrabold text-slate-900">Create account</h1>
  <form method="post" action="<?= e($s('register')) ?>" class="mt-4 space-y-3" data-enter-nav data-enter-submit novalidate>
    <?= csrf_field() ?>
    <div><label class="label" for="name">Your name</label><input class="input py-2 <?= has_err('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e(old('name')) ?>" required maxlength="120" autofocus autocomplete="name"><?= ferr('name') ?></div>
    <div><label class="label" for="phone">Mobile number</label>
      <div class="flex gap-2"><select class="input w-36 py-2" name="phone_code" aria-label="Country code"><?php foreach (Shop::PHONE_CODES as $k => $l): ?><option value="<?= e($k) ?>" <?= old('phone_code', $defCode) === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <input class="input py-2 <?= has_err('phone') ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= e(old('phone')) ?>" required inputmode="tel" autocomplete="tel-national" placeholder="50 123 4567"></div><?= ferr('phone_code') ?><?= ferr('phone') ?></div>
    <div><label class="label" for="email">E-mail</label><input class="input py-2 <?= has_err('email') ? 'is-invalid' : '' ?>" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"><?= ferr('email') ?></div>
    <div><label class="label" for="password">Password</label><input class="input py-2 <?= has_err('password') ? 'is-invalid' : '' ?>" id="password" type="password" name="password" required autocomplete="new-password" data-password-meter="#pw-meter"><div class="mt-1.5 h-1 overflow-hidden rounded bg-slate-100"><div id="pw-meter" class="h-full w-0 bg-rose-500 transition-all"></div></div><p class="help">At least 8 characters with upper-case, lower-case letters and a number.</p><?= ferr('password') ?></div>
    <div><label class="label" for="password_confirmation">Confirm password</label><input class="input py-2 <?= has_err('password_confirmation') ? 'is-invalid' : '' ?>" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"><?= ferr('password_confirmation') ?></div>
    <button class="btn w-full border-amber-300 bg-amber-300 py-2 font-semibold text-slate-900 hover:bg-amber-400" type="submit">Create account</button>
  </form>
  <div class="mt-5 border-t border-slate-100 pt-4 text-center text-sm text-slate-600">Already have an account? <a class="font-semibold text-cyan-700" href="<?= e($s('login')) ?>">Sign in</a></div>
</div>
