<?php $tone = ['ok' => 'text-emerald-600', 'warn' => 'text-amber-600', 'bad' => 'text-rose-600']; $ic = ['ok' => 'circle-check', 'warn' => 'triangle-alert', 'bad' => 'circle-slash']; ?>
<div class="mb-4">
  <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?>Platform<?= icon('chevron-right', 'size-3') ?><span class="font-semibold text-slate-700">System Health</span></nav>
  <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('activity', 'size-6 text-cyan-700') ?>System Health</h1>
  <p class="mt-0.5 max-w-3xl text-sm text-slate-500">Measured live on this server. The application code itself needs only a few milliseconds per request; anything slow below comes from the hosting environment.</p>
</div>
<div class="grid max-w-5xl gap-4">
  <?php foreach ($checks as $group => $rows): ?>
    <section class="card overflow-hidden">
      <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-900"><?= e($group) ?></h2>
      <table class="erp-table">
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="w-8"><?= icon($ic[$r['status']], 'size-4 ' . $tone[$r['status']]) ?></td>
            <td class="w-72 font-medium text-slate-800"><?= e($r['name']) ?></td>
            <td class="font-mono text-xs"><?= e($r['value']) ?><?php if ($r['hint'] && $r['status'] !== 'ok'): ?><span class="mt-1 block font-sans text-xs text-slate-500"><?= e($r['hint']) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>
  <p class="text-sm text-slate-600">This page took <b><?= e((string) $total) ?> ms</b> to build on the server (including all the tests above). Every page also sends a <span class="font-mono">Server-Timing</span> header: open browser DevTools → Network → select a request → <i>Timing</i> to see PHP and database time for that request. If those numbers are small but the page still feels slow, the delay is network or server queueing (LiteSpeed process limits), not the application.</p>
</div>
