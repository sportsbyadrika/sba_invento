<h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Choose a new password</h1>
<p class="mt-1 text-sm text-slate-500">For user <span class="font-mono font-semibold text-slate-800"><?= e($username) ?></span>. At least <?= (int) config('security.password_min_length', 8) ?> characters with upper-case, lower-case letters and a number.</p>
<form method="post" action="<?= e(url('/reset-password/' . $token)) ?>" class="mt-6 space-y-4" data-enter-nav data-enter-submit>
  <?= csrf_field() ?>
  <div>
    <label class="label" for="password">New password</label>
    <input class="input py-2" id="password" name="password" type="password" required minlength="8" maxlength="200" autocomplete="new-password" autofocus data-password-meter="#pw-meter">
    <div class="mt-1.5 h-1 overflow-hidden rounded bg-slate-100"><div id="pw-meter" class="h-full w-0 bg-rose-500 transition-all"></div></div>
  </div>
  <div>
    <label class="label" for="password_confirmation">Confirm password</label>
    <input class="input py-2" id="password_confirmation" name="password_confirmation" type="password" required maxlength="200" autocomplete="new-password">
  </div>
  <button type="submit" class="btn btn-primary w-full py-2"><?= icon('key-round', 'size-4') ?>Save password & unlock</button>
</form>
