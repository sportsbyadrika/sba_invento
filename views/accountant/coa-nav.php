<?php
/** Group hierarchy panel. Only groups are listed; clicking one filters the list to everything under it. */
$bar = ['asset' => 'bg-cyan-500', 'liability' => 'bg-amber-500', 'equity' => 'bg-emerald-500', 'income' => 'bg-indigo-500', 'expense' => 'bg-rose-500'];
$indent = ['', 'pl-4', 'pl-8', 'pl-12', 'pl-16', 'pl-20', 'pl-24'];
?>
<div role="tree" aria-label="Account groups" class="py-1">
  <div class="px-1.5">
    <button type="button" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-100" data-nav-id="" role="treeitem">
      <?= icon('list', 'size-4 text-slate-400') ?><span class="flex-1">All accounts</span><span class="text-[11px] tabular-nums text-slate-400"><?= (int) $total ?></span>
    </button>
  </div>
  <?php foreach ($order as $id): $g = $map[$id]; ?>
    <div class="px-1.5" data-nav-row data-id="<?= (int) $id ?>" data-parent="<?= (int) ($g['parent_id'] ?? 0) ?>" data-depth="<?= (int) $g['depth'] ?>">
      <div class="flex items-center <?= $indent[min((int) $g['depth'], 6)] ?>">
        <?php if ($g['groups']): ?>
          <button type="button" class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700" data-nav-toggle tabindex="-1" aria-label="Expand / collapse"><?= icon('chevron-down', 'size-3.5 transition-transform') ?></button>
        <?php else: ?><span class="w-[18px] shrink-0"></span><?php endif; ?>
        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1.5 py-1 text-left text-[13px] text-slate-700 hover:bg-slate-100 <?= $g['is_active'] ? '' : 'opacity-50' ?>" data-nav-id="<?= (int) $id ?>" role="treeitem" title="<?= e($g['code'] . ' · ' . $g['name']) ?>">
          <span class="h-3.5 w-0.5 shrink-0 rounded <?= $bar[$g['account_type']] ?>"></span>
          <span class="truncate <?= $g['depth'] === 0 ? 'font-semibold text-slate-900' : '' ?>"><?= e($g['name']) ?></span>
          <span class="ml-auto shrink-0 text-[11px] tabular-nums text-slate-400"><?= (int) $g['ledgers'] ?></span>
        </button>
      </div>
    </div>
  <?php endforeach; ?>
</div>
