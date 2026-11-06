<?php /** Global overlays: drawer, confirm dialog, toasts, "Go to" palette, shortcut help */ ?>
<div class="drawer-backdrop hidden" data-drawer-backdrop></div>
<aside class="drawer max-w-2xl" data-drawer role="dialog" aria-modal="true" aria-labelledby="drawer-title" hidden>
  <div data-drawer-body class="flex h-full flex-col"></div>
</aside>

<div class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/40 p-4" data-confirm-dialog role="alertdialog" aria-modal="true">
  <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl">
    <div class="flex gap-3">
      <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600"><?= icon('triangle-alert', 'size-5') ?></span>
      <div>
        <h2 class="font-semibold text-slate-900" data-confirm-title>Please confirm</h2>
        <p class="mt-1 text-sm text-slate-600" data-confirm-text></p>
      </div>
    </div>
    <div class="mt-5 flex justify-end gap-2">
      <button type="button" class="btn" data-confirm-no>Cancel <span class="kbd">Esc</span></button>
      <button type="button" class="btn btn-primary" data-confirm-yes>Yes, continue <span class="kbd border-cyan-500 bg-cyan-600 text-cyan-50">Enter</span></button>
    </div>
  </div>
</div>

<div class="pointer-events-none fixed right-4 bottom-10 z-[90] flex w-80 flex-col gap-2" data-toasts aria-live="polite"></div>

<div class="fixed inset-0 z-[85] hidden items-start justify-center bg-slate-900/30 p-4 pt-[12vh]" data-goto role="dialog" aria-modal="true" aria-label="Go to screen">
  <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-200">
    <div class="flex items-center gap-2 border-b border-slate-200 px-3">
      <?= icon('search', 'size-4 text-slate-400') ?>
      <input type="text" class="w-full border-0 py-3 text-sm outline-none focus:ring-0" placeholder="Go to… (type a screen name, e.g. journal, trial, customers)" data-goto-input autocomplete="off">
      <span class="kbd">Esc</span>
    </div>
    <ul class="max-h-80 overflow-y-auto p-1.5" data-goto-list></ul>
  </div>
  <script type="application/json" data-goto-data><?= json_encode($goto, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
</div>

<div class="fixed inset-0 z-[85] hidden items-center justify-center bg-slate-900/30 p-4" data-shortcuts role="dialog" aria-modal="true" aria-label="Keyboard shortcuts">
  <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-center justify-between">
      <h2 class="font-semibold text-slate-900">Keyboard shortcuts</h2>
      <button type="button" class="btn btn-ghost btn-icon" data-shortcuts-close aria-label="Close"><?= icon('x', 'size-4') ?></button>
    </div>
    <div class="grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2">
      <?php foreach ([
          ['Ctrl K / Alt G', 'Go to any screen'], ['Alt N', 'New record'], ['Alt F', 'Show / hide filter panel'], ['/', 'Search the list'],
          ['↑ ↓', 'Move between rows'], ['Enter', 'Open selected row'], ['Page Up / Down', 'Previous / next page'], ['Alt E', 'Export list to Excel (CSV)'],
          ['Enter (in forms)', 'Move to next field'], ['Shift + Enter', 'Previous field'], ['Ctrl S / Ctrl Enter', 'Save'], ['Esc', 'Close panel / cancel'],
          ['Alt A (journal)', 'Add line'], ['Alt B (journal)', 'Balance the last line'], ['Alt P (journal)', 'Save & post'], ['?', 'This help'],
      ] as [$k, $d]): ?>
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 py-1"><span class="text-slate-600"><?= e($d) ?></span><span class="kbd"><?= e($k) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
