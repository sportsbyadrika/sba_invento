<?php $s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : '')); ?>
<div class="mx-auto max-w-sm rounded-xl border border-slate-200 bg-white p-6">
  <h1 class="text-2xl font-extrabold text-slate-900">Choose a new password</h1>
  <form method="post" action="<?= e($s('reset/' . $token)) ?>" class="mt-4 space-y-3" data-enter-nav data-enter-submit><?= csrf_field() ?>
    <div><label class="label" for="password">New password</label><input class="input py-2" id="password" type="password" name="password" required autofocus autocomplete="new-password"><?= ferr('password') ?></div>
    <div><label class="label" for="password_confirmation">Confirm password</label><input class="input py-2" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"><?= ferr('password_confirmation') ?></div>
    <button class="btn btn-primary w-full py-2" type="submit">Save password</button>
  </form>
</div>
