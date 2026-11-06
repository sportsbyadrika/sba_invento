<?php
$roles = (require BASE_PATH . '/config/permissions.php')['roles'];
$tzs = DateTimeZone::listIdentifiers();
$tabs = ['details' => ['Profile', 'user-round'], 'security' => ['Password & Security', 'key-round'], 'companies' => ['My Companies', 'building-2'], 'logins' => ['Login History', 'shield-check']];
$r = App\Core\Resource::load('profile', 'login-history');
?>
<div class="mb-4">
  <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?><span class="font-semibold text-slate-700">My Profile</span></nav>
  <div class="mt-2 flex items-center gap-3">
    <span class="flex size-12 items-center justify-center rounded-xl bg-slate-900 text-base font-bold text-white"><?= e(strtoupper(mb_substr($user['name'], 0, 1) . mb_substr((string) strrchr(' ' . $user['name'], ' '), 1, 1))) ?></span>
    <div>
      <h1 class="text-xl font-extrabold tracking-tight text-slate-900"><?= e($user['name']) ?></h1>
      <p class="text-sm text-slate-500">@<?= e($user['username']) ?> · last sign-in <?= e(local_dt($user['last_login_at']) ?: '—') ?> from <span class="font-mono"><?= e($user['last_login_ip'] ?? '—') ?></span></p>
    </div>
  </div>
</div>

<div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" role="tablist" data-tabs>
  <?php foreach ($tabs as $k => [$l, $ic]): ?>
    <a href="<?= e(url('/profile', ['tab' => $k])) ?>" role="tab" class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-semibold whitespace-nowrap <?= $tab === $k ? 'border-cyan-700 text-cyan-800' : 'border-transparent text-slate-500 hover:text-slate-800' ?>"><?= icon($ic, 'size-4') ?><?= e($l) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'details'): ?>
  <form class="card max-w-3xl p-5" method="post" action="<?= e(url('/profile')) ?>" data-ajax-form data-enter-nav novalidate>
    <?= csrf_field() ?>
    <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label">Username</label><input class="input font-mono" value="<?= e($user['username']) ?>" disabled><p class="help">Usernames cannot be changed.</p></div>
      <div><label class="label" for="name">Full name</label><input class="input" id="name" name="name" value="<?= e($user['name']) ?>" maxlength="120" required><p class="field-error hidden" data-error-for="name"></p></div>
      <div><label class="label" for="email">E-mail (for password reset)</label><input class="input" id="email" name="email" type="email" value="<?= e($user['email']) ?>" maxlength="150" required><p class="field-error hidden" data-error-for="email"></p></div>
      <div><label class="label" for="phone">Phone</label><input class="input" id="phone" name="phone" value="<?= e($user['phone']) ?>" maxlength="40"></div>
      <div>
        <label class="label" for="timezone">Time zone (dates & login times shown in this zone)</label>
        <select class="input" id="timezone" name="timezone" data-searchable>
          <?php foreach ($tzs as $tz): ?><option value="<?= e($tz) ?>" <?= $tz === display_tz() ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
        </select>
        <p class="field-error hidden" data-error-for="timezone"></p>
      </div>
      <div>
        <label class="label" for="default_company_id">Default company at sign-in</label>
        <select class="input" id="default_company_id" name="default_company_id">
          <option value="">Last used</option>
          <?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $user['default_company_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?>Save profile</button></div>
  </form>

<?php elseif ($tab === 'security'): ?>
  <div class="grid max-w-5xl gap-4 lg:grid-cols-2">
    <form class="card p-5" method="post" action="<?= e(url('/profile/password')) ?>" data-ajax-form data-reset-on-success data-enter-nav novalidate>
      <?= csrf_field() ?>
      <h2 class="mb-1 font-bold text-slate-900">Change password</h2>
      <p class="mb-4 text-sm text-slate-500">At least <?= (int) config('security.password_min_length', 8) ?> characters with upper-case, lower-case letters and a number.</p>
      <?php if ($user['must_change_password']): ?><div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Your administrator asked you to change your password.</div><?php endif; ?>
      <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
      <div class="space-y-3">
        <div><label class="label" for="current_password">Current password</label><input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required><p class="field-error hidden" data-error-for="current_password"></p></div>
        <div><label class="label" for="password">New password</label><input class="input" type="password" id="password" name="password" autocomplete="new-password" required data-password-meter="#pw-meter"><div class="mt-1.5 h-1 overflow-hidden rounded bg-slate-100"><div id="pw-meter" class="h-full w-0 bg-rose-500 transition-all"></div></div><p class="field-error hidden" data-error-for="password"></p></div>
        <div><label class="label" for="password_confirmation">Confirm new password</label><input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
      </div>
      <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary"><?= icon('key-round', 'size-4') ?>Change password</button></div>
    </form>
    <div class="card p-5">
      <h2 class="mb-3 font-bold text-slate-900">Account security</h2>
      <dl class="divide-y divide-slate-100 text-sm">
        <div class="flex justify-between py-2"><dt class="text-slate-500">Status</dt><dd><span class="badge badge-green"><?= e(ucfirst($user['status'])) ?></span></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Password last changed</dt><dd><?= e(local_dt($user['password_changed_at']) ?: 'Never') ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Failed attempts since last login</dt><dd><?= (int) $user['failed_attempts'] ?> / <?= (int) config('security.max_login_attempts', 5) ?></dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Session timeout</dt><dd><?= (int) config('security.session_idle_minutes', 30) ?> min idle</dd></div>
      </dl>
      <p class="mt-3 text-xs text-slate-500">The account locks after <?= (int) config('security.max_login_attempts', 5) ?> consecutive wrong passwords. Unlock it with “Forgot password” or ask your company admin.</p>
    </div>
  </div>

<?php elseif ($tab === 'companies'): ?>
  <div class="grid max-w-5xl gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($companies as $c): $current = (int) $c['id'] === auth()->companyId(); ?>
      <div class="card p-4 <?= $current ? 'ring-2 ring-cyan-600' : '' ?>">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="font-mono text-xs font-bold text-slate-500"><?= e($c['code']) ?></div>
            <div class="font-bold text-slate-900"><?= e($c['name']) ?></div>
            <div class="mt-1 text-xs text-slate-500"><?= e($c['base_currency']) ?> · <?= e($roles[$c['role']]['label'] ?? $c['role']) ?></div>
          </div>
          <?php if ($current): ?><span class="badge badge-cyan">Current</span><?php endif; ?>
        </div>
        <?php if (!$current): ?>
          <form method="post" action="<?= e(url('/switch-company')) ?>" class="mt-3"><?= csrf_field() ?><input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm w-full" type="submit"><?= icon('arrow-left-right', 'size-3.5') ?>Switch to this company</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <?= App\Core\View::partial('partials/list', ['r' => $r]) ?>
<?php endif; ?>
