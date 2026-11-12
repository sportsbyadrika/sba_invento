<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><script src="<?= e(asset('js/app.js')) ?>" defer></script></head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
  <div class="max-w-md text-center">
    <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-white text-slate-400 shadow"><?= icon($status === 404 ? 'search' : 'triangle-alert', 'size-6') ?></div>
    <h1 class="text-2xl font-extrabold text-slate-900"><?= $status === 404 ? 'We couldn’t find that page' : 'Something went wrong' ?></h1>
    <p class="mt-2 text-slate-600"><?= e($message) ?></p>
    <div class="mt-6 flex justify-center gap-2"><button type="button" class="btn" data-history-back>Go back</button><?php if ($home): ?><a class="btn btn-primary" href="<?= e($home) ?>">Continue shopping</a><?php endif; ?></div>
  </div>
</body></html>
