<?php $s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : '')); ?>
<div class="mx-auto max-w-sm rounded-xl border border-slate-200 bg-white p-6">
  <h1 class="text-2xl font-extrabold text-slate-900">Forgot password</h1>
  <p class="mt-1 text-sm text-slate-600">Enter the e-mail of your account and we will send you a link to choose a new password.</p>
  <form method="post" action="<?= e($s('forgot')) ?>" class="mt-4 space-y-3"><?= csrf_field() ?>
    <div><label class="label" for="email">E-mail</label><input class="input py-2" id="email" type="email" name="email" required autofocus></div>
    <button class="btn btn-primary w-full py-2" type="submit">Send reset link</button>
  </form>
</div>
