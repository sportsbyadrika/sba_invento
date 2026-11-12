<?php
/** Public online shop layout. @var array $store @var ?array $customer @var string $sbase */
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$q = (string) ($_GET['q'] ?? '');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(app_prefix()) ?>">
  <title><?= e(($title ?? '') !== $store['store_name'] ? ($title ?? '') . ' · ' : '') ?><?= e($store['store_name']) ?></title>
  <meta name="description" content="<?= e($store['tagline'] ?: 'Shop online at ' . $store['store_name']) ?>">
  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="flex min-h-screen flex-col bg-slate-50">
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
  <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4">
    <a href="<?= e($s()) ?>" class="flex shrink-0 items-center gap-2">
      <span class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-600 to-teal-500 text-white"><?= icon('store', 'size-5') ?></span>
      <span class="hidden leading-tight sm:block"><span class="block text-base font-extrabold text-slate-900"><?= e($store['store_name']) ?></span><?php if ($store['tagline']): ?><span class="block max-w-56 truncate text-[11px] text-slate-500"><?= e($store['tagline']) ?></span><?php endif; ?></span>
    </a>
    <form action="<?= e($s()) ?>" method="get" class="relative mx-auto flex max-w-xl flex-1" role="search">
      <input type="search" name="q" value="<?= e($q) ?>" class="input rounded-r-none py-2 pl-9" placeholder="Search products, brands…" aria-label="Search products">
      <?= icon('search', 'pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400') ?>
      <button class="btn btn-primary rounded-l-none" type="submit" aria-label="Search"><?= icon('search', 'size-4') ?></button>
    </form>
    <nav class="flex shrink-0 items-center gap-1">
      <?php if ($customer): ?>
        <div class="relative" data-dropdown>
          <button type="button" class="btn btn-ghost" data-dropdown-trigger><?= icon('user-round', 'size-5') ?><span class="hidden text-left text-xs leading-tight md:block"><span class="block text-slate-500">Hello,</span><span class="block font-semibold text-slate-900"><?= e(strtok($customer['name'], ' ')) ?></span></span></button>
          <div class="dropdown-panel right-0 hidden w-52" data-dropdown-panel>
            <a class="menu-item" href="<?= e($s('account')) ?>"><?= icon('package', 'size-4 text-slate-500') ?>My orders</a>
            <a class="menu-item" href="<?= e($s('account?tab=items')) ?>"><?= icon('history', 'size-4 text-slate-500') ?>Bought before</a>
            <a class="menu-item" href="<?= e($s('account/addresses')) ?>"><?= icon('map-pin', 'size-4 text-slate-500') ?>Addresses</a>
            <form method="post" action="<?= e($s('logout')) ?>" class="border-t border-slate-100 pt-1"><?= csrf_field() ?><button class="menu-item text-rose-600"><?= icon('log-out', 'size-4') ?>Sign out</button></form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= e($s('login')) ?>" class="btn btn-ghost"><?= icon('user-round', 'size-5') ?><span class="hidden text-xs font-semibold md:inline">Sign in</span></a>
      <?php endif; ?>
      <a href="<?= e($s('cart')) ?>" class="btn btn-ghost relative" aria-label="Cart"><?= icon('shopping-cart', 'size-5') ?><span class="hidden text-xs font-semibold md:inline">Cart</span>
        <?php if ($cartCount): ?><span class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-amber-400 px-1 text-[11px] font-bold text-slate-900"><?= e((string) (float) $cartCount) ?></span><?php endif; ?></a>
    </nav>
  </div>
  <?php if ($categories): ?>
  <nav class="border-t border-slate-100 bg-white" aria-label="Categories">
    <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 py-1.5 text-sm">
      <a href="<?= e($s()) ?>" class="shrink-0 rounded-md px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-100">All</a>
      <?php foreach ($categories as $c): ?><a href="<?= e($s('?category=' . $c['id'])) ?>" class="shrink-0 rounded-md px-2.5 py-1 text-slate-600 hover:bg-slate-100 <?= (int) ($_GET['category'] ?? 0) === (int) $c['id'] ? 'bg-cyan-50 font-semibold text-cyan-800' : '' ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>
</header>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-5">
  <?php foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'error' => 'border-rose-200 bg-rose-50 text-rose-800', 'info' => 'border-sky-200 bg-sky-50 text-sky-800'] as $type => $cls): if ($msg = flash($type)): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border px-3 py-2 text-sm <?= $cls ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" data-flash><?= icon($type === 'error' ? 'triangle-alert' : ($type === 'success' ? 'circle-check' : 'info'), 'size-4 mt-0.5 shrink-0') ?><span class="flex-1"><?= e($msg) ?></span><button type="button" class="opacity-60 hover:opacity-100" data-dismiss aria-label="Dismiss"><?= icon('x', 'size-4') ?></button></div>
  <?php endif; endforeach; ?>
  <?= $content ?>
</main>

<footer class="mt-8 border-t border-slate-200 bg-white">
  <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 text-sm sm:grid-cols-3">
    <div><div class="font-bold text-slate-900"><?= e($store['store_name']) ?></div><p class="mt-1 text-slate-500"><?= e($store['company_name']) ?></p>
      <p class="mt-2 text-slate-600"><?= e(implode(' · ', array_filter([$store['contact_phone'], $store['contact_email']]))) ?></p></div>
    <div><div class="font-bold text-slate-900">Help</div><ul class="mt-2 space-y-1 text-slate-600">
      <li><a class="hover:text-cyan-700" href="<?= e($s('page/shipping')) ?>">Shipping & delivery</a></li><li><a class="hover:text-cyan-700" href="<?= e($s('page/returns')) ?>">Returns & refunds</a></li>
      <li><a class="hover:text-cyan-700" href="<?= e($s('page/terms')) ?>">Terms & conditions</a></li><li><a class="hover:text-cyan-700" href="<?= e($s('page/privacy')) ?>">Privacy policy</a></li></ul></div>
    <div><div class="font-bold text-slate-900">We accept</div><div class="mt-2 flex flex-wrap gap-2 text-xs"><span class="badge badge-green">Cash on Delivery</span><span class="badge badge-slate">Cards & UPI – coming soon</span></div>
      <p class="mt-3 text-xs text-slate-400">Prices include <?= $store['tax_regime'] === 'IN_GST' ? 'GST' : ($store['tax_regime'] === 'UAE_VAT' ? 'VAT' : 'taxes') ?>.</p></div>
  </div>
  <div class="border-t border-slate-100 py-3 text-center text-xs text-slate-400">© <?= date('Y') ?> <?= e($store['store_name']) ?></div>
</footer>
<?php App\Core\Session::forget('_errors'); App\Core\Session::forget('_old'); ?>
</body>
</html>
