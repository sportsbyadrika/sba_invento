<?php
/**
 * Generic list screen: header + overview stats + toolbar + ajax table + right filter panel.
 * @var App\Core\Resource $r
 */
$c = $r->c;
$menu = require BASE_PATH . '/config/menu.php';
$group = null;
foreach ($menu as $g) {
    if ($g['module'] === ($c['menu_module'] ?? $c['module'])) {
        $group = $g['label'];
    }
}
$group ??= ['settings' => 'Settings', 'admin' => 'Platform', 'profile' => 'My Account'][$c['module']] ?? ucfirst($c['module']);
$perPageOptions = (array) config('list.per_page_options', [10, 25, 50, 100, 250]);
$defaultPer = (int) config('list.default_per_page', 25);
$canCreate = $r->allowed('create');
$filters = $c['filters'];
?>
<div data-list
     data-endpoint="<?= e($r->url('data')) ?>"
     data-export="<?= e($r->url('export')) ?>"
     data-form="<?= e($r->url('form')) ?>"
     data-save="<?= e($r->url('save')) ?>"
     data-delete="<?= e($r->url('delete')) ?>"
     data-action="<?= e($r->url('action')) ?>"
     data-new-url="<?= e(isset($c['form_url']) ? url($c['form_url']) : '') ?>"
     data-key="<?= e($r->key) ?>"
     data-per-page="<?= $defaultPer ?>"
     data-drawer-width="<?= e($c['drawer_width']) ?>"
     data-columns="<?= e((string) count($c['columns'])) ?>"
     <?php if (!empty($c['views']['tree'])): ?>data-tree="<?= e(url($c['views']['tree'])) ?>"<?php endif; ?>
     <?php if (!empty($c['views']['nav'])): ?>data-nav="<?= e(url($c['views']['nav'])) ?>" data-nav-filter="<?= e($c['views']['nav_filter']) ?>"<?php endif; ?>>

  <!-- Page header -->
  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <nav class="flex items-center gap-1 text-xs text-slate-500" aria-label="Breadcrumb">
        <a href="<?= e(url('/dashboard')) ?>" class="hover:text-slate-800">Home</a><?= icon('chevron-right', 'size-3') ?><span><?= e($group) ?></span><?= icon('chevron-right', 'size-3') ?><span class="font-semibold text-slate-700"><?= e($c['title']) ?></span>
      </nav>
      <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon($c['icon'], 'size-6 text-cyan-700') ?><?= e($c['title']) ?></h1>
      <?php if ($c['description']): ?><p class="mt-0.5 max-w-3xl text-sm text-slate-500"><?= e($c['description']) ?></p><?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?php if (!empty($c['views']['tree'])): ?>
        <div class="inline-flex rounded-lg border border-slate-200 p-0.5" role="tablist">
          <button type="button" class="btn btn-ghost btn-sm is-active" data-view="list" role="tab"><?= icon('list', 'size-3.5') ?>List</button>
          <button type="button" class="btn btn-ghost btn-sm" data-view="tree" role="tab"><?= icon('git-fork', 'size-3.5') ?>Tree</button>
        </div>
      <?php endif; ?>
      <?php if ($c['export']): ?>
        <button type="button" class="btn" data-export-btn title="Export to Excel / CSV (Alt+E)"><?= icon('file-spreadsheet', 'size-4 text-emerald-700') ?><span class="hidden sm:inline">Export</span></button>
      <?php endif; ?>
      <?php if ($canCreate): ?>
        <button type="button" class="btn btn-primary" data-new title="Alt+N"><?= icon('plus', 'size-4') ?>New <?= e($c['singular']) ?><span class="kbd hidden border-cyan-500 bg-cyan-600 text-cyan-50 sm:inline-flex">Alt N</span></button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Overview / insight cards (recalculated with the active filters) -->
  <?php if ($c['stats']): ?>
  <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4" data-stats>
    <?php foreach ($c['stats'] as $s): ?>
      <div class="card px-4 py-3"><div class="skeleton mb-2 h-3 w-24"></div><div class="skeleton h-6 w-16"></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="flex flex-col gap-4 lg:flex-row lg:items-start" data-list-body>
    <?php if (!empty($c['views']['nav'])): ?>
    <!-- Hierarchy panel: overview of the structure; click a group (or ↑ ↓ Enter) to list everything under it -->
    <aside class="card hidden w-full shrink-0 overflow-hidden lg:sticky lg:top-20 lg:block lg:w-72" data-nav-panel aria-label="Hierarchy">
      <div class="flex items-center gap-1 border-b border-slate-200 px-3 py-2">
        <h2 class="flex flex-1 items-center gap-2 text-sm font-bold text-slate-900"><?= icon('git-fork', 'size-4 text-cyan-700') ?>Hierarchy</h2>
        <button type="button" class="btn btn-ghost btn-icon" data-nav-expand title="Expand all"><?= icon('chevrons-up-down', 'size-3.5') ?></button>
        <button type="button" class="btn btn-ghost btn-icon" data-nav-collapse title="Collapse sub-groups"><?= icon('chevrons-down-up', 'size-3.5') ?></button>
        <button type="button" class="btn btn-ghost btn-icon" data-nav-hide title="Hide hierarchy"><?= icon('x', 'size-3.5') ?></button>
      </div>
      <div class="max-h-[calc(100vh-12rem)] overflow-y-auto" data-nav-body>
        <div class="space-y-2 p-3"><div class="skeleton h-4 w-3/4"></div><div class="skeleton h-4 w-2/3"></div><div class="skeleton h-4 w-1/2"></div></div>
      </div>
      <p class="border-t border-slate-200 px-3 py-1.5 text-[11px] text-slate-500"><span class="kbd">↑ ↓</span> move · <span class="kbd">→ ←</span> open / close · <span class="kbd">Enter</span> list it</p>
    </aside>
    <?php endif; ?>
    <section class="card min-w-0 flex-1 overflow-hidden">
      <!-- Toolbar -->
      <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2">
        <div class="relative min-w-48 flex-1 sm:max-w-xs">
          <?= icon('search', 'pointer-events-none absolute top-2 left-2.5 size-4 text-slate-400') ?>
          <input type="search" class="input pr-8 pl-8" placeholder="Search <?= e(strtolower($c['title'])) ?>…" data-search aria-label="Search" autocomplete="off">
          <span class="kbd pointer-events-none absolute top-1.5 right-2">/</span>
        </div>
        <div class="flex flex-wrap items-center gap-1.5" data-active-filters></div>
        <div class="ml-auto flex items-center gap-2">
          <?php if (!empty($c['views']['nav'])): ?><button type="button" class="btn btn-sm hidden" data-nav-show title="Show hierarchy"><?= icon('git-fork', 'size-3.5') ?>Hierarchy</button><?php endif; ?>
          <button type="button" class="btn btn-ghost btn-sm" data-refresh title="Refresh"><?= icon('refresh-cw', 'size-3.5') ?></button>
          <button type="button" class="btn btn-ghost btn-sm" data-density title="Compact rows"><?= icon('rows-3', 'size-3.5') ?></button>
          <?php if ($filters): ?>
          <button type="button" class="btn btn-sm" data-filter-toggle aria-expanded="false" aria-controls="filter-panel" title="Show / hide filters (Alt+F)">
            <?= icon('sliders-horizontal', 'size-3.5') ?>Filters<span class="badge badge-cyan hidden" data-filter-count>0</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Data table -->
      <div class="relative max-h-[calc(100vh-17rem)] min-h-64 overflow-auto" data-table-wrap>
        <table class="erp-table" data-table>
          <thead>
            <tr>
              <?php foreach ($c['columns'] as $key => $col): $sortable = ($col['sortable'] ?? true) !== false; ?>
                <th scope="col" class="<?= ($col['align'] ?? '') === 'right' ? 'text-right' : '' ?> <?= e($col['width'] ?? '') ?>" <?= $sortable ? 'data-sort="' . e($key) . '" tabindex="0"' : '' ?>>
                  <span class="inline-flex items-center gap-1"><?= e($col['label']) ?><span data-sort-icon class="opacity-0"><?= icon('arrow-up-down', 'size-3') ?></span></span>
                </th>
              <?php endforeach; ?>
              <th scope="col" class="w-24 text-right"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody data-rows>
            <?php for ($i = 0; $i < 8; $i++): ?>
              <tr><?php foreach ($c['columns'] as $col): ?><td><div class="skeleton h-4 w-full max-w-32"></div></td><?php endforeach; ?><td></td></tr>
            <?php endfor; ?>
          </tbody>
        </table>
        <div class="hidden flex-col items-center justify-center gap-2 py-16 text-center" data-empty>
          <span class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><?= icon($c['icon'], 'size-6') ?></span>
          <p class="font-semibold text-slate-700">No <?= e(strtolower($c['title'])) ?> found</p>
          <p class="text-sm text-slate-500" data-empty-hint>Try changing the search or filters.</p>
          <?php if ($canCreate): ?><button type="button" class="btn btn-primary mt-2" data-new><?= icon('plus', 'size-4') ?>Create <?= e(strtolower($c['singular'])) ?></button><?php endif; ?>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-3 py-2 text-sm text-slate-600">
        <div class="flex items-center gap-1.5">
          <label for="per-page-<?= e(str_slug($r->key)) ?>" class="text-xs text-slate-500">Rows</label>
          <select id="per-page-<?= e(str_slug($r->key)) ?>" class="input w-auto py-1 text-xs" data-per-page-select>
            <?php foreach ($perPageOptions as $n): ?><option value="<?= (int) $n ?>" <?= $n === $defaultPer ? 'selected' : '' ?>><?= (int) $n ?></option><?php endforeach; ?>
          </select>
        </div>
        <span class="text-xs" data-summary>Loading…</span>
        <div class="ml-auto flex items-center gap-1" data-pager></div>
      </div>
    </section>

    <!-- Right side filter panel (hidden by default, toggled with the Filters button / Alt+F) -->
    <?php if ($filters): ?>
    <aside id="filter-panel" class="filter-panel card hidden lg:sticky lg:top-20" data-filter-panel aria-label="Filters">
      <form data-filter-form class="flex max-h-[calc(100vh-7rem)] flex-col" autocomplete="off">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
          <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900"><?= icon('sliders-horizontal', 'size-4 text-cyan-700') ?>Filters</h2>
          <button type="button" class="btn btn-ghost btn-icon" data-filter-close aria-label="Hide filters"><?= icon('x', 'size-4') ?></button>
        </div>
        <div class="flex-1 space-y-4 overflow-y-auto px-4 py-3" data-enter-nav>
          <?php foreach ($filters as $key => $f): ?>
            <div>
              <span class="label"><?= e($f['label']) ?></span>
              <?php if ($f['type'] === 'daterange' || $f['type'] === 'numrange'): $t = $f['type'] === 'daterange' ? 'date' : 'number'; ?>
                <div class="grid grid-cols-2 gap-2">
                  <input type="<?= $t ?>" class="input" name="f[<?= e($key) ?>_from]" placeholder="From" aria-label="<?= e($f['label']) ?> from" <?= $t === 'number' ? 'step="any"' : '' ?> data-filter-label="<?= e($f['label']) ?> ≥">
                  <input type="<?= $t ?>" class="input" name="f[<?= e($key) ?>_to]" placeholder="To" aria-label="<?= e($f['label']) ?> to" <?= $t === 'number' ? 'step="any"' : '' ?> data-filter-label="<?= e($f['label']) ?> ≤">
                </div>
                <?php if ($f['type'] === 'daterange'): ?>
                  <div class="mt-1.5 flex flex-wrap gap-1" data-date-presets="<?= e($key) ?>">
                    <?php foreach (['today' => 'Today', 'month' => 'This month', 'last-month' => 'Last month', 'quarter' => 'This quarter', 'year' => 'This year'] as $p => $pl): ?>
                      <button type="button" class="btn btn-sm px-1.5 py-0.5 text-[11px]" data-preset="<?= $p ?>"><?= e($pl) ?></button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php elseif ($f['type'] === 'text'): ?>
                <input type="text" class="input" name="f[<?= e($key) ?>]" data-filter-label="<?= e($f['label']) ?>">
              <?php else: $opts = $r->filterOptions($key); ?>
                <?php if (count($opts) <= 6 && empty($f['dropdown'])): ?>
                  <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($opts as $v => $l): ?>
                      <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-700 has-[:checked]:border-cyan-300 has-[:checked]:bg-cyan-50 has-[:checked]:text-cyan-900">
                        <input type="checkbox" class="check size-3.5" name="f[<?= e($key) ?>][]" value="<?= e($v) ?>" data-filter-label="<?= e($f['label']) ?>" data-option-label="<?= e($l) ?>"><?= e($l) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <select class="input" name="f[<?= e($key) ?>]" data-filter-label="<?= e($f['label']) ?>">
                    <option value="">All</option>
                    <?php foreach ($opts as $v => $l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?>
                  </select>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="flex gap-2 border-t border-slate-200 px-4 py-3">
          <button type="button" class="btn flex-1" data-filter-reset><?= icon('rotate-ccw', 'size-3.5') ?>Reset</button>
          <button type="submit" class="btn btn-primary flex-1"><?= icon('check', 'size-3.5') ?>Apply</button>
        </div>
      </form>
    </aside>
    <?php endif; ?>
  </div>

  <?php if (!empty($c['views']['tree'])): ?>
  <section class="card hidden" data-tree-panel><div class="p-6 text-sm text-slate-500">Loading tree…</div></section>
  <?php endif; ?>
</div>
