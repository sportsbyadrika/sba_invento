<?php
/** Receive goods against a purchase order (GRN). @var array $doc */
$open = array_filter($doc['lines'], static fn ($l) => (float) $l['qty'] - (float) $l['received_qty'] > 0.00001);
$q = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
?>
<form method="post" action="<?= e(url('/purchases/orders/' . $doc['id'] . '/receive')) ?>" data-ajax-form data-redirect-on-success data-enter-nav data-receive autocomplete="off">
  <?= csrf_field() ?>
  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/purchases/orders')) ?>">Purchase Orders</a><?= icon('chevron-right', 'size-3') ?><a href="<?= e(url('/purchases/orders/' . $doc['id'])) ?>"><?= e($doc['number']) ?></a></nav>
      <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('package-check', 'size-6 text-cyan-700') ?>Receive goods · <span class="font-mono"><?= e($doc['number']) ?></span></h1>
      <p class="text-sm text-slate-500"><?= e($doc['contact_name']) ?> · each received line becomes a stock batch at <b>landed cost</b> = price after discount + share of freight (by value).</p>
    </div>
    <div class="flex gap-2"><a href="<?= e(url('/purchases/orders/' . $doc['id'])) ?>" class="btn">Cancel</a><button type="submit" class="btn btn-success" data-save-btn><?= icon('package-check', 'size-4') ?>Receive into stock</button></div>
  </div>
  <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
  <section class="card p-4">
    <div class="grid gap-3 sm:grid-cols-4">
      <div><label class="label" for="receipt_date">Received on</label><input type="date" class="input" id="receipt_date" name="receipt_date" value="<?= e(date('Y-m-d')) ?>"><p class="field-error hidden" data-error-for="receipt_date"></p></div>
      <div><label class="label" for="bill_ref">Vendor bill / delivery note no.</label><input class="input" id="bill_ref" name="bill_ref" maxlength="40"></div>
      <div><label class="label" for="freight">Freight for this delivery (<?= e($doc['currency_code']) ?>)</label><input class="input text-right tabular-nums" id="freight" name="freight" inputmode="decimal" value="<?= e($q($doc['freight'])) ?>" data-r-freight></div>
      <div class="flex items-end text-xs text-slate-500">Freight is added to the cost of these goods; it is not a separate expense.</div>
    </div>
  </section>
  <section class="card mt-4 overflow-x-auto">
    <table class="erp-table">
      <thead><tr><th>Item</th><th class="text-right">Ordered</th><th class="text-right">Already received</th><th class="text-right">In stock now</th><th class="w-32 text-right">Receive now</th><th class="text-right">Price after disc.</th><th class="text-right">Landed unit cost</th><th class="w-36 text-right">New selling price</th></tr></thead>
      <tbody>
      <?php foreach ($open as $l): $net = (float) $l['line_total'] / max((float) $l['qty'], 0.0001); $it = $items[(int) $l['item_id']] ?? null; ?>
        <tr data-r-line data-net="<?= e((string) $net) ?>">
          <td><span class="font-mono text-xs text-slate-500"><?= e($l['sku']) ?></span> <span class="font-medium text-slate-900"><?= e($l['item_name'] ?: $l['description']) ?></span><?php if ($l['item_id'] && !(int) $l['track_inventory']): ?> <span class="badge badge-slate">Service – expensed</span><?php endif; ?></td>
          <td class="text-right tabular-nums"><?= e($q($l['qty'])) ?></td>
          <td class="text-right tabular-nums"><?= e($q($l['received_qty'])) ?></td>
          <td class="text-right tabular-nums text-slate-500"><?= $l['item_id'] ? e($q($onHand[(int) $l['item_id']] ?? 0)) : '' ?></td>
          <td><input class="input text-right tabular-nums" name="receive[<?= (int) $l['id'] ?>]" value="<?= e($q((float) $l['qty'] - (float) $l['received_qty'])) ?>" inputmode="decimal" data-r-qty><p class="field-error hidden" data-error-for="receive.<?= (int) $l['id'] ?>"></p></td>
          <td class="text-right tabular-nums"><?= money($net) ?></td>
          <td class="text-right font-semibold tabular-nums" data-r-cost><?= money($net) ?></td>
          <td><?php if ($it): ?><input class="input text-right tabular-nums" name="price[<?= (int) $it['id'] ?>]" value="<?= e($q($it['sales_price'])) ?>" inputmode="decimal" title="Current selling price - change it if the new cost needs a new price"><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <p class="mt-3 text-xs text-slate-500">Older stock keeps its own cost: sales use the oldest batch first (FIFO). The selling price above is the single price customers see in the shop and on new invoices.</p>
</form>
