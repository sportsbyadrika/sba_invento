<?php
use App\Services\Shop;
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$cur = $store['base_currency'];
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-2">
  <div><h1 class="text-2xl font-extrabold text-slate-900">Your account</h1><p class="text-sm text-slate-500"><?= e($customer['name']) ?> · <?= e($customer['email']) ?> · <?= e($customer['phone_code'] . ' ' . $customer['phone']) ?></p></div>
  <a href="<?= e($s('account/addresses')) ?>" class="btn"><?= icon('map-pin', 'size-4') ?>Addresses</a>
</div>
<div class="mb-4 flex gap-1 border-b border-slate-200 text-sm">
  <a href="<?= e($s('account')) ?>" class="-mb-px border-b-2 px-3 py-2 font-semibold <?= $tab === 'orders' ? 'border-cyan-700 text-cyan-800' : 'border-transparent text-slate-500' ?>">Orders</a>
  <a href="<?= e($s('account?tab=items')) ?>" class="-mb-px border-b-2 px-3 py-2 font-semibold <?= $tab === 'items' ? 'border-cyan-700 text-cyan-800' : 'border-transparent text-slate-500' ?>">Bought before</a>
</div>
<?php if ($tab === 'orders'): ?>
  <?php if (!$orders): ?><div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-500">No orders yet. <a class="font-semibold text-cyan-700" href="<?= e($s()) ?>">Start shopping</a></div><?php endif; ?>
  <div class="space-y-3">
  <?php foreach ($orders as $o): ?>
    <a href="<?= e($s('account/orders/' . $o['number'])) ?>" class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 hover:border-cyan-300">
      <span class="size-14 shrink-0 overflow-hidden rounded bg-slate-100"><?php if ($o['image']): ?><img src="<?= e(upload_url($o['image'])) ?>" alt="" class="size-full object-cover"><?php endif; ?></span>
      <span class="min-w-0 flex-1 text-sm"><span class="font-mono font-semibold text-slate-900"><?= e($o['number']) ?></span> · <?= e(local_dt($o['placed_at'], 'd M Y')) ?><span class="block text-slate-500"><?= (int) $o['lines_count'] ?> item<?= (int) $o['lines_count'] === 1 ? '' : 's' ?> · <?= $o['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online' ?></span></span>
      <span class="text-right"><?= App\Core\Resource::badge(Shop::STAGES, $o['status']) ?><span class="mt-1 block font-bold text-slate-900"><?= e($cur) ?> <?= money($o['total']) ?></span></span>
    </a>
  <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="erp-table"><thead><tr><th>Item</th><th>Order</th><th>Date</th><th class="text-right">Qty</th><th class="text-right">Price paid (incl. tax)</th><th class="text-right">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($bought as $b): $unit = ((float) $b['line_total'] + (float) $b['tax_amount']) / max((float) $b['qty'], 0.0001); ?>
      <tr><td><span class="font-medium text-slate-900"><?= e($b['name']) ?></span> <span class="font-mono text-xs text-slate-400"><?= e($b['sku']) ?></span></td>
        <td><a class="font-mono text-xs text-cyan-700" href="<?= e($s('account/orders/' . $b['number'])) ?>"><?= e($b['number']) ?></a></td>
        <td><?= e(local_dt($b['placed_at'], 'd M Y')) ?></td><td class="text-right"><?= e((string) (float) $b['qty']) ?></td>
        <td class="text-right tabular-nums"><?= e($cur) ?> <?= money($unit) ?></td><td class="text-right tabular-nums font-semibold"><?= money((float) $b['line_total'] + (float) $b['tax_amount']) ?></td>
        <td class="text-right"><form method="post" action="<?= e($s('cart/add')) ?>"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $b['item_id'] ?>"><button class="btn btn-sm">Buy again</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endif; ?>
