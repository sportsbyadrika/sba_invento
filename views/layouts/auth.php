<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(base_path_url()) ?>">
  <meta name="robots" content="noindex">
  <title><?= e($title ?? 'Sign in') ?> · <?= e(config('app.name')) ?></title>
  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="min-h-screen bg-white">
  <div class="grid min-h-screen lg:grid-cols-[1fr_1.1fr]">
    <section class="flex flex-col justify-between px-6 py-8 sm:px-12">
      <a href="<?= e(url('/login')) ?>" class="flex items-center gap-2">
        <span class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-600 to-teal-500 text-white"><?= icon('boxes', 'size-5') ?></span>
        <span class="text-lg font-extrabold tracking-tight text-slate-900"><?= e(config('app.name')) ?></span>
      </a>
      <div class="mx-auto w-full max-w-sm py-10">
        <?php foreach (['success', 'error', 'info'] as $type): if ($msg = flash($type)): ?>
          <div class="mb-5 flex gap-2 rounded-lg border px-3 py-2.5 text-sm <?= $type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="alert">
            <?= icon($type === 'error' ? 'triangle-alert' : 'circle-check', 'size-4 mt-0.5 shrink-0') ?><span><?= e($msg) ?></span>
          </div>
        <?php endif; endforeach; ?>
        <?= $content ?>
      </div>
      <p class="text-xs text-slate-400">© <?= date('Y') ?> <?= e(config('app.name')) ?> · Protected by account lock-out, login audit and encrypted sessions.</p>
    </section>
    <aside class="relative hidden overflow-hidden border-l border-slate-200 bg-slate-50 lg:block">
      <div class="absolute inset-0 bg-[radial-gradient(#cbd5e1_1px,transparent_1px)] [background-size:22px_22px] opacity-60"></div>
      <div class="relative flex h-full flex-col justify-center px-14">
        <span class="badge badge-cyan mb-4 w-fit">Accounting · Inventory · Sales · Purchases</span>
        <h2 class="max-w-md text-3xl font-extrabold tracking-tight text-slate-900">Your books, stock and taxes — in one fast, keyboard-friendly workspace.</h2>
        <p class="mt-3 max-w-md text-slate-600">Multi-company, multi-currency, UAE VAT & India GST ready. Built for teams that live in spreadsheets and desktop accounting software.</p>
        <div class="mt-8 grid max-w-md grid-cols-2 gap-3 text-sm">
          <?php foreach ([['git-fork', 'Chart of accounts tree'], ['book-open', 'Double-entry journals'], ['coins', 'Multi-currency'], ['shield-check', 'Login audit & lock-out'], ['keyboard', 'Enter-key data entry'], ['building-2', 'Switch companies']] as [$ic, $t]): ?>
            <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-700"><?= icon($ic, 'size-4 text-cyan-700') ?><?= e($t) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>
  </div>
</body>
</html>
