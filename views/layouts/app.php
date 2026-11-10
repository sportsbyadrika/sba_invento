<?php
/** @var string $content */
$auth = auth();
$user = $auth->user();
$company = $auth->company();
$menu = require BASE_PATH . '/config/menu.php';
$activeModule = $activeModule ?? '';
$companies = $auth->companies();
$initials = strtoupper(implode('', array_map(static fn ($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim((string) ($user['name'] ?? 'U'))) ?: ['U'], 0, 2))));
$roles = (require BASE_PATH . '/config/permissions.php')['roles'];
$roleLabel = $auth->isSuper() ? 'Super Admin' : ($roles[$auth->role()]['label'] ?? '');
$fyStart = $company ? App\Controllers\DashboardController::fiscalYearStart($company) : null;
$fyLabel = $fyStart ? 'FY ' . date('Y', strtotime($fyStart)) . ((int) $company['fiscal_year_start'] === 1 ? '' : '-' . date('y', strtotime($fyStart . ' +1 year'))) : '';
$settingsMenu = [
    ['Company Profile', '/settings/company', 'building-2'],
    ['Users & Access', '/settings/users', 'users'],
    ['Currencies', '/settings/currencies', 'coins'],
    ['Exchange Rates', '/settings/exchange-rates', 'refresh-cw'],
    ['Tax Rates', '/settings/taxes', 'percent'],
    ['Document Numbering', '/settings/number-series', 'hash'],
    ['Login Log', '/settings/login-logs', 'shield-check'],
    ['Audit Trail', '/settings/audit-log', 'history'],
];
$platformMenu = [
    ['Companies', '/admin/companies', 'building-2'],
    ['All Users', '/admin/users', 'users'],
    ['Platform Login Log', '/admin/login-logs', 'shield-check'],
    ['System Health', '/admin/system', 'activity'],
];
// Quick "Go to" list (Ctrl+K / Alt+G) - only screens the user may open
$goto = [];
foreach ($menu as $g) {
    if (!can($g['module'])) {
        continue;
    }
    if (isset($g['url'])) {
        $goto[] = ['label' => $g['label'], 'group' => '', 'url' => url($g['url'])];
    }
    foreach ($g['children'] ?? [] as $c) {
        $goto[] = ['label' => $c['label'], 'group' => $g['label'], 'url' => url($c['url']), 'soon' => !empty($c['planned'])];
    }
}
if ($company && $auth->isAdmin()) {
    foreach ($settingsMenu as [$l, $u]) {
        $goto[] = ['label' => $l, 'group' => 'Settings', 'url' => url($u)];
    }
}
if ($auth->isSuper()) {
    foreach ($platformMenu as [$l, $u]) {
        $goto[] = ['label' => $l, 'group' => 'Platform', 'url' => url($u)];
    }
}
$goto[] = ['label' => 'My Profile', 'group' => 'Account', 'url' => url('/profile')];
$goto[] = ['label' => 'New Journal', 'group' => 'Create', 'url' => url('/accountant/journals/new')];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(app_prefix()) ?>">
  <meta name="robots" content="noindex">
  <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?><?= e(config('app.name')) ?></title>
  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="flex min-h-screen flex-col bg-white">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-[100] btn">Skip to content</a>

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
  <div class="flex h-14 items-center gap-2 px-3 sm:px-4 lg:px-6">
    <button type="button" class="btn btn-ghost btn-icon lg:hidden" data-toggle="#mobile-nav" aria-label="Open menu"><?= icon('menu', 'size-5') ?></button>

    <a href="<?= e(url('/dashboard')) ?>" class="mr-2 flex shrink-0 items-center gap-2" aria-label="Dashboard">
      <span class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-600 to-teal-500 text-white shadow-sm"><?= icon('boxes', 'size-[18px]') ?></span>
      <span class="hidden text-[15px] font-extrabold tracking-tight text-slate-900 sm:block"><?= e(config('app.name')) ?></span>
    </a>

    <!-- Main menu (top navbar) -->
    <nav class="hidden min-w-0 flex-1 items-center gap-0.5 lg:flex" aria-label="Main">
      <?php foreach ($menu as $i => $g): if (!can($g['module'])) { continue; } $isActive = $activeModule === $g['module']; ?>
        <?php if (empty($g['children'])): ?>
          <a href="<?= e(url($g['url'])) ?>" class="nav-link <?= $isActive ? 'is-active' : '' ?>"><?= icon($g['icon'], 'hidden size-4 opacity-70 2xl:block') ?><?= e($g['label']) ?></a>
        <?php else: ?>
          <div class="relative" data-dropdown>
            <button type="button" class="nav-link <?= $isActive ? 'is-active' : '' ?>" data-dropdown-trigger aria-expanded="false" aria-haspopup="true">
              <?= icon($g['icon'], 'hidden size-4 opacity-70 2xl:block') ?><?= e($g['label']) ?><?= icon('chevron-down', 'hidden size-3.5 opacity-50 xl:block') ?>
            </button>
            <div class="dropdown-panel left-0 hidden w-72" data-dropdown-panel role="menu">
              <div class="px-2.5 pt-1.5 pb-1 section-title"><?= e($g['label']) ?></div>
              <?php foreach ($g['children'] as $c): ?>
                <?php if (!empty($c['planned'])): ?>
                  <a href="<?= e(url($c['url'])) ?>" class="menu-item text-slate-400" role="menuitem">
                    <?= icon($c['icon'] ?? 'circle', 'size-4 opacity-60') ?><span class="flex-1"><?= e($c['label']) ?></span>
                    <span class="badge badge-slate">Phase <?= (int) $c['planned'] ?></span>
                  </a>
                <?php else: ?>
                  <a href="<?= e(url($c['url'])) ?>" class="menu-item" role="menuitem">
                    <span class="flex size-7 items-center justify-center rounded-md bg-cyan-50 text-cyan-700"><?= icon($c['icon'] ?? 'circle', 'size-4') ?></span>
                    <span class="flex-1 leading-tight"><span class="block font-medium"><?= e($c['label']) ?></span><?php if (!empty($c['hint'])): ?><span class="block text-xs text-slate-500"><?= e($c['hint']) ?></span><?php endif; ?></span>
                  </a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="ml-auto flex items-center gap-1">
      <button type="button" class="btn btn-ghost hidden text-slate-500 md:inline-flex" data-goto-open title="Go to screen (Ctrl+K / Alt+G)">
        <?= icon('search', 'size-4') ?><span class="hidden text-xs 2xl:inline">Go to…</span><span class="kbd hidden 2xl:inline-flex">Ctrl K</span>
      </button>

      <?php if ($company && $auth->isAdmin()): ?>
      <div class="relative" data-dropdown>
        <button type="button" class="btn btn-ghost btn-icon <?= $activeModule === 'settings' ? 'is-active' : '' ?>" data-dropdown-trigger aria-label="Company settings" title="Company settings"><?= icon('settings', 'size-[18px]') ?></button>
        <div class="dropdown-panel right-0 hidden w-60" data-dropdown-panel role="menu">
          <div class="px-2.5 pt-1.5 pb-1 section-title">Company settings</div>
          <?php foreach ($settingsMenu as [$l, $u, $ic]): ?>
            <a href="<?= e(url($u)) ?>" class="menu-item" role="menuitem"><?= icon($ic, 'size-4 text-slate-500') ?><?= e($l) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($auth->isSuper()): ?>
      <div class="relative" data-dropdown>
        <button type="button" class="btn btn-ghost btn-icon <?= $activeModule === 'admin' ? 'is-active' : '' ?>" data-dropdown-trigger aria-label="Platform administration" title="Platform administration"><?= icon('server-cog', 'size-[18px]') ?></button>
        <div class="dropdown-panel right-0 hidden w-60" data-dropdown-panel role="menu">
          <div class="px-2.5 pt-1.5 pb-1 section-title">Platform (super admin)</div>
          <?php foreach ($platformMenu as [$l, $u, $ic]): ?>
            <a href="<?= e(url($u)) ?>" class="menu-item" role="menuitem"><?= icon($ic, 'size-4 text-slate-500') ?><?= e($l) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Profile menu: user, companies (switch), profile, logout -->
      <div class="relative" data-dropdown>
        <button type="button" class="flex items-center gap-2 rounded-lg py-1 pr-1.5 pl-1 hover:bg-slate-100" data-dropdown-trigger aria-label="Profile menu">
          <span class="flex size-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-bold text-white"><?= e($initials) ?></span>
          <span class="hidden max-w-44 text-left leading-tight xl:block">
            <span class="block truncate text-[13px] font-semibold text-slate-900"><?= e($user['name'] ?? '') ?></span>
            <span class="block truncate text-[11px] text-slate-500"><?= e($company['name'] ?? 'No company selected') ?></span>
          </span>
          <?= icon('chevron-down', 'size-3.5 text-slate-400') ?>
        </button>
        <div class="dropdown-panel right-0 hidden w-80" data-dropdown-panel role="menu">
          <div class="flex items-center gap-3 border-b border-slate-100 px-2.5 pt-2 pb-3">
            <span class="flex size-10 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white"><?= e($initials) ?></span>
            <div class="min-w-0 leading-tight">
              <div class="truncate font-semibold text-slate-900"><?= e($user['name'] ?? '') ?></div>
              <div class="truncate text-xs text-slate-500">@<?= e($user['username'] ?? '') ?> · <?= e($roleLabel) ?></div>
            </div>
          </div>
          <?php if ($companies): ?>
          <div class="border-b border-slate-100 py-1.5">
            <div class="flex items-center justify-between px-2.5 pb-1"><span class="section-title">Companies</span><span class="text-[11px] text-slate-400"><?= count($companies) ?> linked</span></div>
            <div class="max-h-60 overflow-y-auto">
            <?php foreach ($companies as $c): $current = (int) $c['id'] === (int) ($company['id'] ?? 0); ?>
              <form method="post" action="<?= e(url('/switch-company')) ?>">
                <?= csrf_field() ?><input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="menu-item <?= $current ? 'bg-cyan-50 text-cyan-900' : '' ?>" <?= $current ? 'disabled aria-current="true"' : '' ?>>
                  <span class="flex size-7 shrink-0 items-center justify-center rounded-md <?= $current ? 'bg-cyan-700 text-white' : 'bg-slate-100 text-slate-600' ?> font-mono text-[10px] font-bold"><?= e(mb_substr($c['code'], 0, 3)) ?></span>
                  <span class="min-w-0 flex-1 leading-tight"><span class="block truncate font-medium"><?= e($c['name']) ?></span><span class="block text-[11px] text-slate-500"><?= e($c['base_currency']) ?> · <?= e($roles[$c['role']]['label'] ?? $c['role']) ?></span></span>
                  <?php if ($current): ?><?= icon('check', 'size-4 text-cyan-700') ?><?php endif; ?>
                </button>
              </form>
            <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="py-1">
            <a href="<?= e(url('/profile')) ?>" class="menu-item"><?= icon('user-round', 'size-4 text-slate-500') ?>My Profile</a>
            <a href="<?= e(url('/profile', ['tab' => 'security'])) ?>" class="menu-item"><?= icon('key-round', 'size-4 text-slate-500') ?>Password & Security</a>
            <a href="<?= e(url('/profile', ['tab' => 'logins'])) ?>" class="menu-item"><?= icon('shield-check', 'size-4 text-slate-500') ?>Login History</a>
            <button type="button" class="menu-item" data-shortcuts-open><?= icon('keyboard', 'size-4 text-slate-500') ?>Keyboard Shortcuts<span class="kbd ml-auto">?</span></button>
          </div>
          <div class="border-t border-slate-100 pt-1">
            <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?>
              <button type="submit" class="menu-item font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700"><?= icon('log-out', 'size-4') ?>Sign out</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Mobile navigation -->
  <nav id="mobile-nav" class="hidden max-h-[75vh] overflow-y-auto border-t border-slate-200 px-3 py-2 lg:hidden" aria-label="Main mobile">
    <?php foreach ($menu as $g): if (!can($g['module'])) { continue; } ?>
      <?php if (empty($g['children'])): ?>
        <a href="<?= e(url($g['url'])) ?>" class="menu-item font-semibold"><?= icon($g['icon'], 'size-4') ?><?= e($g['label']) ?></a>
      <?php else: ?>
        <details class="group" <?= $activeModule === $g['module'] ? 'open' : '' ?>>
          <summary class="menu-item cursor-pointer list-none font-semibold"><?= icon($g['icon'], 'size-4') ?><span class="flex-1"><?= e($g['label']) ?></span><?= icon('chevron-down', 'size-4 transition group-open:rotate-180') ?></summary>
          <div class="pb-1 pl-7">
            <?php foreach ($g['children'] as $c): ?>
              <a href="<?= e(url($c['url'])) ?>" class="menu-item <?= !empty($c['planned']) ? 'text-slate-400' : '' ?>"><?= e($c['label']) ?><?php if (!empty($c['planned'])): ?><span class="badge badge-slate ml-auto">Phase <?= (int) $c['planned'] ?></span><?php endif; ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</header>

<main id="main" class="w-full flex-1 px-3 py-4 sm:px-4 lg:px-6 lg:py-5">
  <?php foreach (['success' => 'badge-green', 'error' => 'badge-rose', 'info' => 'badge-blue'] as $type => $cls): if ($msg = flash($type)): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border px-3 py-2 text-sm <?= $type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : ($type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-sky-200 bg-sky-50 text-sky-800') ?>" data-flash>
      <?= icon($type === 'error' ? 'triangle-alert' : ($type === 'success' ? 'circle-check' : 'info'), 'size-4 mt-0.5 shrink-0') ?><span class="flex-1"><?= e($msg) ?></span>
      <button type="button" class="opacity-60 hover:opacity-100" data-dismiss aria-label="Dismiss"><?= icon('x', 'size-4') ?></button>
    </div>
  <?php endif; endforeach; ?>
  <?= $content ?>
</main>

<!-- Status bar (desktop-app feel: company, FY, currency, shortcuts) -->
<footer class="no-print sticky bottom-0 z-30 hidden border-t border-slate-200 bg-slate-50/95 px-4 py-1 text-[11px] text-slate-500 backdrop-blur md:block lg:px-6">
  <div class="flex items-center gap-4">
    <?php if ($company): ?>
      <span class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-emerald-500"></span><span class="font-semibold text-slate-700"><?= e($company['name']) ?></span></span>
      <span><?= e($fyLabel) ?></span>
      <span>Base: <span class="font-mono font-semibold text-slate-700"><?= e($company['base_currency']) ?></span></span>
      <span><?= e(['UAE_VAT' => 'UAE VAT', 'IN_GST' => 'India GST', 'NONE' => 'No tax'][$company['tax_regime']] ?? '') ?><?= $company['tax_number'] ? ' · ' . e($company['tax_number']) : '' ?></span>
    <?php endif; ?>
    <span class="ml-auto hidden items-center gap-3 xl:flex">
      <span><span class="kbd">Alt N</span> New</span><span><span class="kbd">Alt F</span> Filters</span><span><span class="kbd">/</span> Search</span><span><span class="kbd">Enter</span> Next field</span><span><span class="kbd">Ctrl S</span> Save</span><span><span class="kbd">Esc</span> Close</span><span><span class="kbd">?</span> All shortcuts</span>
    </span>
    <span class="xl:hidden ml-auto"><span class="kbd">?</span> Shortcuts</span>
  </div>
</footer>

<?= App\Core\View::partial('partials/overlays', ['goto' => $goto]) ?>
</body>
</html>
