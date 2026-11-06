<h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Forgot password</h1>
<p class="mt-1 text-sm text-slate-500">Enter your username or e-mail. We will send a reset link to the e-mail address registered on your account. The link also unlocks a locked account.</p>
<form method="post" action="<?= e(url('/forgot-password')) ?>" class="mt-6 space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="label" for="login">Username or e-mail</label>
    <input class="input py-2" id="login" name="login" required maxlength="150" autocomplete="username" autofocus>
  </div>
  <button type="submit" class="btn btn-primary w-full py-2"><?= icon('mail', 'size-4') ?>Send reset link</button>
  <a href="<?= e(url('/login')) ?>" class="flex items-center justify-center gap-1 text-sm font-semibold text-slate-600 hover:text-slate-900"><?= icon('arrow-left', 'size-4') ?>Back to sign in</a>
</form>
