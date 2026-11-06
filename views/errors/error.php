<div class="mx-auto max-w-lg py-16 text-center">
  <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500"><?= icon($status === 404 ? 'search' : ($status === 403 ? 'lock' : 'triangle-alert'), 'size-6') ?></div>
  <p class="font-mono text-sm font-semibold text-cyan-700"><?= (int) $status ?></p>
  <h1 class="mt-1 text-2xl font-extrabold text-slate-900"><?= e(['404' => 'Page not found', '403' => 'Access denied', '419' => 'Session expired', '405' => 'Not allowed'][(string) $status] ?? 'Something went wrong') ?></h1>
  <p class="mt-2 text-slate-600"><?= e($message) ?></p>
  <div class="mt-6 flex justify-center gap-2">
    <button type="button" class="btn" data-history-back><?= icon('arrow-left', 'size-4') ?>Go back</button>
    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary"><?= icon('layout-dashboard', 'size-4') ?>Dashboard</a>
  </div>
</div>
