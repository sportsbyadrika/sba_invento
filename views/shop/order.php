<?php
use App\Services\Shop;
$cur = $o['currency_code'];
$st = $o['status'];
$i = array_search($st, Shop::FLOW, true);
$next = $i !== false && $i < count(Shop::FLOW) - 1 ? Shop::FLOW[$i + 1] : null;
$labels = ['confirmed' => ['Confirm order', 'circle-check'], 'packed' => ['Mark packed', 'package'], 'shipped' => ['Ship & invoice', 'truck'], 'out_for_delivery' => ['Out for delivery', 'map-pin'], 'delivered' => ['Mark delivered', 'circle-check']];
$q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div>
    <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/shop/dispatch')) ?>">Dispatch</a><?= icon('chevron-right', 'size-3') ?><a href="<?= e(url('/shop/orders')) ?>">Online Orders</a></nav>
    <h1 class="mt-1 flex flex-wrap items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('shopping-cart', 'size-6 text-cyan-700') ?>Order <span class="font-mono"><?= e($o['number']) ?></span> <?= App\Core\Resource::badge(Shop::STAGES, $st) ?>
      <?= $o['payment_status'] === 'paid' ? '<span class="badge badge-green">Paid</span>' : ($o['payment_status'] === 'refunded' ? '<span class="badge badge-violet">Refunded</span>' : '<span class="badge badge-amber">' . ($o['payment_method'] === 'cod' ? 'COD - collect on delivery' : 'Payment due') . '</span>') ?></h1>
    <p class="text-sm text-slate-500">Placed <?= e(local_dt($o['placed_at'])) ?> · <?= e($o['customer_name']) ?> · <?= e($o['phone_code'] . ' ' . $o['customer_phone']) ?> · <?= e($o['customer_email']) ?></p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= e(url('/shop/orders/' . $o['id'] . '/packing-slip')) ?>" target="_blank" rel="noopener" class="btn"><?= icon('printer', 'size-4') ?>Packing slip</a>
    <?php if ($o['invoice']): ?><a href="<?= e(url('/sales/invoices/' . $o['invoice']['id'])) ?>" class="btn"><?= icon('receipt', 'size-4') ?>Invoice <?= e($o['invoice']['number']) ?></a><?php endif; ?>
  </div>
</div>

<?php if ($canEdit && $st !== 'cancelled' && $st !== 'delivered'): ?>
<section class="card mb-4 p-4">
  <div class="flex flex-wrap items-start gap-4">
    <?php if ($next): ?>
    <form method="post" action="<?= e(url('/shop/orders/' . $o['id'] . '/advance')) ?>" class="flex flex-1 flex-wrap items-end gap-3" data-ajax-form data-redirect-on-success>
      <?= csrf_field() ?><input type="hidden" name="to" value="<?= e($next) ?>">
      <div class="mb-1 w-full text-sm font-semibold text-slate-700">Next step: <?= e(Shop::STAGES[$next][0]) ?></div>
      <div class="hidden w-full rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
      <?php if ($next === 'shipped'): ?>
        <div><label class="label" for="courier">Courier</label><input class="input" id="courier" name="courier" maxlength="60" placeholder="Aramex / own vehicle" value="<?= e($o['courier']) ?>"></div>
        <div><label class="label" for="tracking_no">Tracking / AWB no.</label><input class="input" id="tracking_no" name="tracking_no" maxlength="60" value="<?= e($o['tracking_no']) ?>"></div>
      <?php endif; ?>
      <?php if ($next === 'delivered' && $o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid'): ?>
        <div><label class="label" for="account_id">Cash received into</label><select class="input" id="account_id" name="account_id"><?php foreach ($cashAccounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="min-w-48 flex-1"><label class="label" for="note">Note to customer (optional)</label><input class="input" id="note" name="note" maxlength="300"></div>
      <button type="submit" class="btn btn-success" data-save-btn><?= icon($labels[$next][1], 'size-4') ?><?= e($labels[$next][0]) ?></button>
      <?php if ($next === 'shipped'): ?><p class="w-full text-xs text-slate-500">Shipping posts the tax invoice and issues the stock (FIFO cost).</p><?php endif; ?>
      <?php if ($next === 'delivered' && $o['payment_method'] === 'cod'): ?><p class="w-full text-xs text-slate-500">Marking delivered records the COD cash against the invoice.</p><?php endif; ?>
    </form>
    <?php endif; ?>
    <?php if (in_array($st, ['placed', 'confirmed', 'packed'], true)): ?>
    <form method="post" action="<?= e(url('/shop/orders/' . $o['id'] . '/advance')) ?>" data-ajax-form data-redirect-on-success data-confirm-submit="Cancel this order? The reserved stock is released.">
      <?= csrf_field() ?><input type="hidden" name="to" value="cancelled"><input type="hidden" name="note" value="Cancelled by store">
      <button type="submit" class="btn btn-danger mt-6"><?= icon('ban', 'size-4') ?>Cancel order</button>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<div class="grid gap-4 xl:grid-cols-3">
  <section class="card overflow-x-auto xl:col-span-2">
    <table class="erp-table">
      <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">In stock</th><th class="text-right">Price (ex tax)</th><th class="text-right">Tax</th><th class="text-right">Total</th></tr></thead>
      <tbody>
      <?php foreach ($o['lines'] as $l): $stock = $onHand[(int) $l['item_id']] ?? 0; $short = !$o['invoice_id'] && in_array($st, ['placed', 'confirmed', 'packed'], true) && $stock < (float) $l['qty']; ?>
        <tr>
          <td><div class="flex items-center gap-2"><span class="size-10 shrink-0 overflow-hidden rounded bg-slate-100"><?php if ($l['image']): ?><img src="<?= e(upload_url($l['image'])) ?>" alt="" class="size-full object-cover"><?php endif; ?></span><span><span class="font-medium text-slate-900"><?= e($l['name']) ?></span><span class="block font-mono text-xs text-slate-500"><?= e($l['sku']) ?></span></span></div></td>
          <td class="text-right tabular-nums font-semibold"><?= e($q($l['qty'])) ?><?php if ((float) $l['returned_qty'] > 0): ?><span class="block text-xs text-violet-700">returned <?= e($q($l['returned_qty'])) ?></span><?php endif; ?></td>
          <td class="text-right tabular-nums <?= $short ? 'font-semibold text-rose-600' : 'text-slate-500' ?>"><?= e($q($stock)) ?><?= $short ? ' · short' : '' ?></td>
          <td class="text-right tabular-nums"><?= money($l['unit_price']) ?></td>
          <td class="text-right tabular-nums text-slate-600"><?= e($q($l['tax_pct'])) ?>% · <?= money($l['tax_amount']) ?></td>
          <td class="text-right tabular-nums font-medium"><?= money((float) $l['line_total'] + (float) $l['tax_amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="text-sm">
        <tr><td colspan="5" class="px-3 py-1 text-right text-slate-500">Items (incl. tax)</td><td class="px-3 py-1 text-right tabular-nums"><?= money((float) $o['subtotal'] + (float) $o['tax_total']) ?></td></tr>
        <tr><td colspan="5" class="px-3 py-1 text-right text-slate-500">Delivery</td><td class="px-3 py-1 text-right tabular-nums"><?= money($o['delivery_fee']) ?></td></tr>
        <?php if ((float) $o['cod_fee']): ?><tr><td colspan="5" class="px-3 py-1 text-right text-slate-500">COD fee</td><td class="px-3 py-1 text-right tabular-nums"><?= money($o['cod_fee']) ?></td></tr><?php endif; ?>
        <tr class="bg-slate-50 font-extrabold"><td colspan="5" class="px-3 py-2 text-right">Total <?= e($cur) ?></td><td class="px-3 py-2 text-right tabular-nums"><?= money($o['total']) ?></td></tr>
      </tfoot>
    </table>
  </section>
  <div class="space-y-4">
    <section class="card p-4 text-sm">
      <h2 class="font-bold text-slate-900">Deliver to</h2>
      <p class="mt-1 text-slate-700"><span class="badge badge-slate"><?= e($o['ship_label']) ?></span><br><b><?= e($o['ship_name']) ?></b> · <?= e($o['ship_phone']) ?><br><?= nl2br(e($o['ship_address'])) ?><br><?= e($o['ship_city']) ?> <?= e($o['ship_region']) ?></p>
      <?php if ($o['customer_note']): ?><p class="mt-2 rounded-md bg-amber-50 px-2 py-1 text-amber-900">“<?= e($o['customer_note']) ?>”</p><?php endif; ?>
      <?php if ($o['tracking_no'] || $o['courier']): ?><p class="mt-2 text-slate-700"><?= icon('truck', 'size-4 inline text-cyan-700') ?> <?= e($o['courier']) ?> <span class="font-mono"><?= e($o['tracking_no']) ?></span></p><?php endif; ?>
    </section>
    <section class="card p-4 text-sm">
      <h2 class="font-bold text-slate-900">Timeline</h2>
      <ol class="mt-2 space-y-2 border-l-2 border-slate-200 pl-3">
        <?php foreach (array_reverse($o['events']) as $ev): ?><li><span class="font-semibold text-slate-800"><?= e(Shop::STAGES[$ev['status']][0] ?? ucwords(str_replace('_', ' ', $ev['status']))) ?></span> <span class="text-xs text-slate-500"><?= e(local_dt($ev['created_at'])) ?> · <?= e($ev['actor']) ?></span><?php if ($ev['note']): ?><span class="block text-slate-600"><?= e($ev['note']) ?></span><?php endif; ?></li><?php endforeach; ?>
      </ol>
    </section>
    <?php if ($o['returns']): ?><section class="card p-4 text-sm"><h2 class="font-bold text-slate-900">Returns</h2>
      <?php foreach ($o['returns'] as $r): ?><a class="mt-2 flex justify-between hover:text-cyan-700" href="<?= e(url('/shop/returns/' . $r['id'])) ?>"><span class="font-mono text-xs"><?= e($r['number']) ?></span><?= App\Core\Resource::badge(Shop::RETURN_STATUS, $r['status']) ?></a><?php endforeach; ?></section><?php endif; ?>
  </div>
</div>
