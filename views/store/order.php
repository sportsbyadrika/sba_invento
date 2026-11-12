<?php
use App\Services\Shop;
$s = static fn (string $p = '') => url($sbase . ($p !== '' ? '/' . ltrim($p, '/') : ''));
$cur = $store['base_currency'];
$flowIdx = array_search($o['status'], Shop::FLOW, true);
$eventsBy = [];
foreach ($o['events'] as $ev) { $eventsBy[$ev['status']] ??= $ev; }
?>
<nav class="mb-3 text-xs text-slate-500"><a href="<?= e($s('account')) ?>" class="hover:text-cyan-700">Your orders</a> › <?= e($o['number']) ?></nav>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div><h1 class="text-2xl font-extrabold text-slate-900">Order <span class="font-mono"><?= e($o['number']) ?></span></h1>
    <p class="text-sm text-slate-500">Placed <?= e(local_dt($o['placed_at'])) ?> · <?= $o['payment_method'] === 'cod' ? 'Cash on delivery' : 'Online payment' ?> · <?= $o['payment_status'] === 'paid' ? '<span class="font-semibold text-emerald-700">Paid</span>' : ($o['payment_status'] === 'refunded' ? 'Refunded' : 'Payment due on delivery') ?></p></div>
  <div class="flex flex-wrap gap-2">
    <?php if ($o['invoice_id']): ?><a class="btn" href="<?= e($s('account/orders/' . $o['number'] . '/invoice')) ?>" target="_blank" rel="noopener"><?= icon('receipt', 'size-4') ?>Invoice</a><?php endif; ?>
    <?php if ($canCancel): ?><form method="post" action="<?= e($s('account/orders/' . $o['number'] . '/cancel')) ?>" data-confirm-submit="Cancel this order?"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Cancel order</button></form><?php endif; ?>
  </div>
</div>

<section class="rounded-xl border border-slate-200 bg-white p-5">
  <?php if ($o['status'] === 'cancelled'): ?>
    <p class="font-semibold text-slate-700"><?= icon('circle-slash', 'size-4 inline text-slate-400') ?> This order was cancelled<?= $o['cancelled_at'] ? ' on ' . e(local_dt($o['cancelled_at'])) : '' ?>.</p>
  <?php else: ?>
  <ol class="grid grid-cols-3 gap-y-4 sm:grid-cols-6" aria-label="Delivery progress">
    <?php foreach (Shop::FLOW as $i => $st): $done = $i <= $flowIdx; ?>
      <li class="relative flex flex-col items-center text-center">
        <?php if ($i > 0): ?><span class="absolute top-3 right-1/2 h-0.5 w-full <?= $done ? 'bg-emerald-500' : 'bg-slate-200' ?>"></span><?php endif; ?>
        <span class="relative z-10 flex size-7 items-center justify-center rounded-full text-xs font-bold <?= $done ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500' ?>"><?= $done ? '✓' : $i + 1 ?></span>
        <span class="mt-1 text-xs font-semibold <?= $done ? 'text-slate-900' : 'text-slate-400' ?>"><?= e(Shop::STAGES[$st][0]) ?></span>
        <?php if (isset($eventsBy[$st])): ?><span class="text-[11px] text-slate-500"><?= e(local_dt($eventsBy[$st]['created_at'], 'd M, H:i')) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if ($o['tracking_no']): ?><p class="mt-4 text-sm text-slate-700"><?= icon('truck', 'size-4 inline text-cyan-700') ?> Shipped with <b><?= e($o['courier']) ?></b> · tracking no. <span class="font-mono font-semibold"><?= e($o['tracking_no']) ?></span></p><?php endif; ?>
  <?php endif; ?>
</section>

<div class="mt-4 grid gap-4 lg:grid-cols-[1fr_20rem]">
  <section class="rounded-xl border border-slate-200 bg-white">
    <?php foreach ($o['lines'] as $l): $unit = ((float) $l['line_total'] + (float) $l['tax_amount']) / max((float) $l['qty'], 0.0001); ?>
      <div class="flex gap-3 border-b border-slate-100 p-4 text-sm">
        <span class="size-16 shrink-0 overflow-hidden rounded bg-slate-100"><?php if ($l['image']): ?><img src="<?= e(upload_url($l['image'])) ?>" alt="" class="size-full object-cover"><?php endif; ?></span>
        <div class="flex-1"><div class="font-medium text-slate-900"><?= e($l['name']) ?></div><div class="text-slate-500"><?= e((string) (float) $l['qty']) ?> × <?= e($cur) ?> <?= money($unit) ?> <span class="text-xs">(price at purchase, incl. tax)</span></div>
          <?php if ((float) $l['returned_qty'] > 0): ?><div class="text-xs font-semibold text-violet-700">Returned: <?= e((string) (float) $l['returned_qty']) ?></div><?php endif; ?>
          <?php if (in_array((int) $l['item_id'], $reviewable, true)): ?>
            <details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-cyan-700">Write a review</summary>
              <form method="post" action="<?= e($s('account/orders/' . $o['number'] . '/review')) ?>" class="mt-2 space-y-2"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= (int) $l['item_id'] ?>">
                <div class="flex gap-3 text-sm"><?php for ($r = 5; $r >= 1; $r--): ?><label class="flex items-center gap-1"><input type="radio" class="check" name="rating" value="<?= $r ?>" <?= $r === 5 ? 'checked' : '' ?>><span class="text-amber-500"><?= str_repeat('★', $r) ?></span></label><?php endfor; ?></div>
                <input class="input" name="title" maxlength="120" placeholder="Headline">
                <textarea class="input" name="body" rows="2" maxlength="2000" placeholder="What did you like or dislike?"></textarea>
                <button class="btn btn-sm btn-primary" type="submit">Submit review</button>
              </form></details>
          <?php endif; ?>
        </div>
        <div class="font-semibold"><?= e($cur) ?> <?= money((float) $l['line_total'] + (float) $l['tax_amount']) ?></div>
      </div>
    <?php endforeach; ?>
  </section>
  <aside class="space-y-4">
    <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
      <h2 class="font-bold text-slate-900">Delivery address</h2>
      <p class="mt-1 text-slate-700"><span class="badge badge-slate"><?= e($o['ship_label']) ?></span><br><b><?= e($o['ship_name']) ?></b> · <?= e($o['ship_phone']) ?><br><?= nl2br(e($o['ship_address'])) ?><br><?= e($o['ship_city']) ?></p>
    </section>
    <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
      <h2 class="font-bold text-slate-900">Payment summary</h2>
      <dl class="mt-2 space-y-1">
        <div class="flex justify-between"><dt class="text-slate-600">Items</dt><dd><?= money((float) $o['subtotal'] + (float) $o['tax_total']) ?></dd></div>
        <div class="flex justify-between"><dt class="text-slate-600">Delivery</dt><dd><?= (float) $o['delivery_fee'] ? money($o['delivery_fee']) : 'FREE' ?></dd></div>
        <?php if ((float) $o['cod_fee']): ?><div class="flex justify-between"><dt class="text-slate-600">COD fee</dt><dd><?= money($o['cod_fee']) ?></dd></div><?php endif; ?>
        <div class="flex justify-between border-t border-slate-200 pt-1 font-extrabold"><dt>Total</dt><dd><?= e($cur) ?> <?= money($o['total']) ?></dd></div>
        <div class="text-xs text-slate-500">Includes tax <?= money($o['tax_total']) ?></div>
      </dl>
    </section>
    <?php if ($o['returns']): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
      <h2 class="font-bold text-slate-900">Returns</h2>
      <?php foreach ($o['returns'] as $r): ?><div class="mt-2 flex justify-between"><span class="font-mono text-xs"><?= e($r['number']) ?></span><?= App\Core\Resource::badge(Shop::RETURN_STATUS, $r['status']) ?></div><div class="text-xs text-slate-500">Refund <?= e($cur) ?> <?= money($r['refund_amount']) ?></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>

<?php if ($canReturn): ?>
<section id="return" class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
  <h2 class="text-lg font-bold text-slate-900">Return items</h2>
  <p class="text-sm text-slate-500">Returns are accepted within <?= (int) $store['return_days'] ?> days of delivery. We will collect the item and refund you after inspection.</p>
  <form method="post" action="<?= e($s('account/orders/' . $o['number'] . '/return')) ?>" class="mt-3 space-y-3"><?= csrf_field() ?>
    <table class="erp-table"><thead><tr><th>Item</th><th class="text-right">Bought</th><th class="w-32 text-right">Return qty</th></tr></thead><tbody>
      <?php foreach ($o['lines'] as $l): $max = (float) $l['qty'] - (float) $l['returned_qty']; if ($max <= 0) { continue; } ?>
        <tr><td><?= e($l['name']) ?></td><td class="text-right"><?= e((string) (float) $l['qty']) ?></td><td><select class="input" name="qty[<?= (int) $l['id'] ?>]"><?php for ($i = 0; $i <= $max; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <div class="grid gap-3 sm:grid-cols-2">
      <div><label class="label" for="reason">Reason</label><select class="input" id="reason" name="reason"><?php foreach ($reasons as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select><?= ferr('reason') ?></div>
      <div><label class="label" for="details">Details</label><input class="input" id="details" name="details" maxlength="1000" placeholder="Describe the problem (optional)"></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('undo-2', 'size-4') ?>Request return</button>
  </form>
</section>
<?php endif; ?>

<section class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
  <h2 class="font-bold text-slate-900">Order history</h2>
  <ol class="mt-2 space-y-2 border-l-2 border-slate-200 pl-4 text-sm">
    <?php foreach (array_reverse($o['events']) as $ev): ?><li><span class="font-semibold text-slate-800"><?= e(Shop::STAGES[$ev['status']][0] ?? ucwords(str_replace('_', ' ', $ev['status']))) ?></span> <span class="text-xs text-slate-500"><?= e(local_dt($ev['created_at'])) ?></span><?php if ($ev['note']): ?><span class="block text-slate-600"><?= e($ev['note']) ?></span><?php endif; ?></li><?php endforeach; ?>
  </ol>
</section>
