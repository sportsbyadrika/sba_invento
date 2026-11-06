<h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Sign in</h1>
<p class="mt-1 text-sm text-slate-500">Use the username given by your company administrator.</p>
<form method="post" action="<?= e(url('/login')) ?>" class="mt-6 space-y-4" data-enter-nav data-enter-submit data-login-form autocomplete="on">
  <?= csrf_field() ?>
  <input type="hidden" name="client_tz"><input type="hidden" name="client_screen"><input type="hidden" name="client_platform"><input type="hidden" name="client_lang">
  <div>
    <label class="label" for="username">Username</label>
    <input class="input py-2" id="username" name="username" value="<?= e(old('username')) ?>" required maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus>
  </div>
  <div>
    <div class="flex items-center justify-between"><label class="label" for="password">Password</label><a href="<?= e(url('/forgot-password')) ?>" class="mb-1 text-xs font-semibold text-cyan-700 hover:underline">Forgot password?</a></div>
    <div class="relative">
      <input class="input py-2 pr-10" id="password" name="password" type="password" required maxlength="200" autocomplete="current-password">
      <button type="button" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-700" data-toggle-password="#password" aria-label="Show password"><?= icon('eye', 'size-4') ?></button>
    </div>
  </div>
  <button type="submit" class="btn btn-primary w-full py-2"><?= icon('log-in', 'size-4') ?>Sign in</button>
  <p class="text-xs text-slate-500">After <?= (int) config('security.max_login_attempts', 5) ?> failed attempts the account is locked. Use “Forgot password” to unlock it with your registered e-mail.</p>
</form>
