<?php $s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : '')); ?>
<div class="mx-auto max-w-sm rounded-xl border border-slate-200 bg-white p-6">
  <h1 class="text-2xl font-extrabold text-slate-900">Sign in</h1>
  <form method="post" action="<?= e($s('login')) ?>" class="mt-4 space-y-3" data-enter-nav data-enter-submit>
    <?= csrf_field() ?>
    <div><label class="label" for="login">E-mail or mobile number</label><input class="input py-2" id="login" name="login" value="<?= e(old('login')) ?>" required autofocus autocomplete="username"></div>
    <div><div class="flex justify-between"><label class="label" for="password">Password</label><a class="mb-1 text-xs font-semibold text-cyan-700" href="<?= e($s('forgot')) ?>">Forgot password?</a></div><input class="input py-2" id="password" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn w-full border-amber-300 bg-amber-300 py-2 font-semibold text-slate-900 hover:bg-amber-400" type="submit">Sign in</button>
  </form>
  <div class="mt-5 border-t border-slate-100 pt-4 text-center text-sm text-slate-600">New customer? <a class="font-semibold text-cyan-700" href="<?= e($s('register')) ?>">Create your account</a></div>
</div>
