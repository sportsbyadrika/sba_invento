<?php
/** Chart of accounts tree with closing balances. @var array $map @var array $order */
$tone = ['asset' => 'border-cyan-400', 'liability' => 'border-amber-400', 'equity' => 'border-emerald-400', 'income' => 'border-indigo-400', 'expense' => 'border-rose-400'];
$indent = ['', 'pl-5', 'pl-10', 'pl-14', 'pl-20', 'pl-24', 'pl-28', 'pl-32'];
$dc = static fn (float $v) => abs($v) < 0.005 ? '<span class="text-slate-300">0.00</span>' : money(abs($v)) . ' <span class="text-[10px] font-semibold text-slate-400">' . ($v >= 0 ? 'Dr' : 'Cr') . '</span>';
?>
<div data-coa-tree>
  <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2">
    <div class="relative min-w-48 flex-1 sm:max-w-xs">
      <?= icon('search', 'pointer-events-none absolute top-2 left-2.5 size-4 text-slate-400') ?>
      <input type="search" class="input pl-8" placeholder="Find account in tree…" data-tree-search>
    </div>
    <button type="button" class="btn btn-sm" data-tree-expand><?= icon('chevrons-up-down', 'size-3.5') ?>Expand all</button>
    <button type="button" class="btn btn-sm" data-tree-collapse><?= icon('chevrons-down-up', 'size-3.5') ?>Collapse to groups</button>
    <span class="ml-auto text-xs text-slate-500">Closing balances as of today (posted journals + opening balances)</span>
  </div>
  <div class="overflow-x-auto">
    <table class="erp-table">
      <thead><tr><th>Account</th><th class="w-28">Code</th><th class="w-28">Type</th><th class="w-44 text-right">Balance</th><th class="w-28 text-right"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($order as $id): $a = $map[$id]; ?>
        <tr data-tree-row data-id="<?= (int) $id ?>" data-parent="<?= (int) ($a['parent_id'] ?? 0) ?>" data-depth="<?= (int) $a['depth'] ?>" data-group="<?= (int) $a['is_group'] ?>" data-text="<?= e(strtolower($a['code'] . ' ' . $a['name'])) ?>" class="<?= $a['is_active'] ? '' : 'opacity-50' ?>">
          <td>
            <div class="flex items-center gap-1.5 <?= $indent[min((int) $a['depth'], 7)] ?>">
              <?php if ($a['is_group']): ?>
                <button type="button" class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700" data-tree-toggle aria-expanded="true" aria-label="Toggle"><?= icon('chevron-down', 'size-4 transition-transform') ?></button>
                <span class="border-l-[3px] <?= $tone[$a['account_type']] ?> pl-2 font-semibold text-slate-900"><?= e($a['name']) ?></span>
                <span class="text-[11px] text-slate-400"><?= count($a['children']) ?></span>
              <?php else: ?>
                <span class="w-5"></span><span class="text-slate-700"><?= e($a['name']) ?></span>
                <?php if ($a['sub_type']): ?><span class="badge badge-slate"><?= e(str_replace('_', ' ', $a['sub_type'])) ?></span><?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
          <td class="font-mono text-xs font-semibold text-slate-600"><?= e($a['code']) ?></td>
          <td><?= App\Core\Resource::badge(['asset' => ['Asset', 'cyan'], 'liability' => ['Liability', 'amber'], 'equity' => ['Equity', 'emerald'], 'income' => ['Income', 'indigo'], 'expense' => ['Expense', 'rose']], $a['account_type']) ?></td>
          <td class="text-right tabular-nums <?= $a['is_group'] ? 'font-semibold text-slate-900' : '' ?>"><?= $dc($a['cb']) ?></td>
          <td class="text-right whitespace-nowrap">
            <?php if (!$a['is_group']): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/reports/ledger', ['account_id' => $id])) ?>" title="Ledger"><?= icon('book-open', 'size-3.5') ?></a><?php endif; ?>
            <?php if ($canEdit && $a['is_group']): ?><button type="button" class="btn btn-ghost btn-sm" data-tree-add="<?= (int) $id ?>" title="Add sub-account"><?= icon('plus', 'size-3.5') ?></button><?php endif; ?>
            <?php if ($canEdit): ?><button type="button" class="btn btn-ghost btn-sm" data-tree-edit="<?= (int) $id ?>" title="Edit"><?= icon('pencil', 'size-3.5') ?></button><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
