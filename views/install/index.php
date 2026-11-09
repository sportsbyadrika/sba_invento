<?php $old = $old ?? []; ?>
<h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Install <?= e(config('app.name')) ?></h1>
<p class="mt-1 text-sm text-slate-500">Creates the database tables and the platform super admin. This page switches itself off when it finishes.</p>

<ul class="mt-5 space-y-1 rounded-lg border border-slate-200 p-3 text-sm">
  <?php foreach ($checks as [$label, $ok, $hint]): ?>
    <li class="flex items-start gap-2"><?= icon($ok ? 'circle-check' : 'triangle-alert', 'size-4 mt-0.5 shrink-0 ' . ($ok ? 'text-emerald-600' : 'text-amber-600')) ?>
      <span><?= e($label) ?><?php if (!$ok): ?><span class="block text-xs text-slate-500"><?= e($hint) ?></span><?php endif; ?></span></li>
  <?php endforeach; ?>
</ul>

<?php foreach (array_filter([$dbError ?? null, $error ?? null]) as $msg): ?>
  <div class="mt-4 flex gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-800" role="alert"><?= icon('triangle-alert', 'size-4 mt-0.5 shrink-0') ?><span><?= e($msg) ?></span></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url('/install')) ?>" class="mt-5 space-y-3" data-enter-nav autocomplete="off">
  <?= csrf_field() ?>
  <div><label class="label" for="install_key">Install key <span class="font-normal text-slate-400">(app.install_key in config.php)</span></label><input class="input" id="install_key" name="install_key" type="password" required autofocus></div>
  <div class="section-title pt-2">Super admin account</div>
  <div class="grid gap-3 sm:grid-cols-2">
    <div><label class="label" for="username">Username</label><input class="input" id="username" name="username" value="<?= e($old['username'] ?? 'superadmin') ?>" required maxlength="50"></div>
    <div><label class="label" for="name">Full name</label><input class="input" id="name" name="name" value="<?= e($old['name'] ?? 'Platform Super Admin') ?>" maxlength="120"></div>
  </div>
  <div><label class="label" for="email">E-mail (for password reset)</label><input class="input" id="email" name="email" type="email" value="<?= e($old['email'] ?? '') ?>" required></div>
  <div class="grid gap-3 sm:grid-cols-2">
    <div><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
    <div><label class="label" for="password_confirmation">Confirm password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
  </div>
  <p class="text-xs text-slate-500">At least 8 characters with upper-case, lower-case letters and a number.</p>
  <label class="flex items-start gap-2 text-sm"><input type="checkbox" class="check mt-0.5" name="demo" value="1"><span>Also load <b>demo data</b> (2 sample companies, users <span class="font-mono">admin / accountant / sales</span>). Leave unticked for the live site.</span></label>
  <button type="submit" class="btn btn-primary w-full py-2" <?= !empty($dbError) ? 'disabled' : '' ?>><?= icon('play', 'size-4') ?>Install now</button>
</form>
