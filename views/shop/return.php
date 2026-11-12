<?php
use App\Services\Shop;
$st = $r['status'];
?>
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
  <div>
    <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/shop/returns')) ?>">Returns</a></nav>
    <h1 class="mt-1 flex flex-wrap items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('undo-2', 'size-6 text-cyan-700') ?>Return <span class="font-mono"><?= e($r['number']) ?></span> <?= App\Core\Resource::badge(Shop::RETURN_STATUS, $st) ?></h1>
    <p class="text-sm text-slate-500">Order <a class="font-mono text-cyan-700" href="<?= e(url('/shop/orders/' . $r['order_id'])) ?>"><?= e($r['order_number']) ?></a> · <?= e($r['customer_name']) ?> · <?= e($r['phone_code'] . ' ' . $r['phone']) ?> · requested <?= e(local_dt($r['created_at'])) ?></p>
  </div>
  <?php if ($r['credit_note']): ?><a class="btn" href="<?= e(url('/sales/credit-notes/' . $r['credit_note']['id'])) ?>"><?= icon('file-minus', 'size-4') ?>Credit note <?= e($r['credit_note']['number']) ?></a><?php endif; ?>
</div>
<div class="grid gap-4 xl:grid-cols-3">
  <section class="card overflow-x-auto xl:col-span-2">
    <table class="erp-table"><thead><tr><th>Item</th><th class="text-right">Bought</th><th class="text-right">Return qty</th><th class="text-right">Refund (incl. tax)</th></tr></thead><tbody>
      <?php foreach ($r['lines'] as $l): ?><tr><td><span class="font-medium text-slate-900"><?= e($l['name']) ?></span> <span class="font-mono text-xs text-slate-500"><?= e($l['sku']) ?></span></td><td class="text-right"><?= e((string) (float) $l['ordered']) ?></td><td class="text-right font-semibold"><?= e((string) (float) $l['qty']) ?></td><td class="text-right tabular-nums"><?= money($l['amount']) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot><tr class="bg-slate-50 font-extrabold"><td colspan="3" class="px-3 py-2 text-right">Refund total</td><td class="px-3 py-2 text-right tabular-nums"><?= money($r['refund_amount']) ?></td></tr></tfoot></table>
    <div class="border-t border-slate-200 p-4 text-sm"><span class="label">Reason</span><b><?= e(Shop::RETURN_REASONS[$r['reason']] ?? $r['reason']) ?></b><?php if ($r['details']): ?><p class="mt-1 text-slate-700">“<?= e($r['details']) ?>”</p><?php endif; ?>
      <?php if ($r['staff_note']): ?><p class="mt-2 text-slate-500">Staff note: <?= e($r['staff_note']) ?></p><?php endif; ?>
      <?php if ($r['refund_ref']): ?><p class="mt-2 text-slate-500">Refund reference: <?= e($r['refund_ref']) ?> (<?= e($r['refund_method']) ?>)</p><?php endif; ?></div>
  </section>
  <section class="card p-4">
    <?php if (!$canEdit || in_array($st, ['rejected', 'refunded'], true)): ?>
      <p class="text-sm text-slate-600"><?= $st === 'refunded' ? 'This return is complete.' : ($st === 'rejected' ? 'This return was rejected.' : 'Read only.') ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/shop/returns/' . $r['id'] . '/process')) ?>" class="space-y-3" data-ajax-form data-redirect-on-success>
      <?= csrf_field() ?>
      <div class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
      <?php if ($st === 'requested'): ?>
        <h2 class="font-bold text-slate-900">Decision</h2>
        <div><label class="label" for="note">Note to customer</label><input class="input" id="note" name="note" maxlength="300" placeholder="e.g. Courier will collect on Monday"></div>
        <div class="flex gap-2"><button class="btn btn-success flex-1" name="op" value="approve" type="submit"><?= icon('check', 'size-4') ?>Approve</button><button class="btn btn-danger flex-1" name="op" value="reject" type="submit">Reject</button></div>
      <?php elseif ($st === 'approved'): ?>
        <h2 class="font-bold text-slate-900">Goods received back</h2>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="restock" value="1" checked>Put the items back into sellable stock (untick if damaged)</label>
        <div><label class="label" for="note">Inspection note</label><input class="input" id="note" name="note" maxlength="300"></div>
        <button class="btn btn-success w-full" name="op" value="receive" type="submit"><?= icon('package-check', 'size-4') ?>Receive & create credit note</button>
        <p class="text-xs text-slate-500">Creates a posted credit note (reverses the sale and tax) and adds restocked items back at their original FIFO cost.</p>
      <?php elseif ($st === 'received'): ?>
        <h2 class="font-bold text-slate-900">Refund <?= money($r['credit_note']['total'] ?? $r['refund_amount']) ?></h2>
        <div><label class="label" for="account_id">Paid from</label><select class="input" id="account_id" name="account_id"><?php foreach ($cashAccounts as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $a['sub_type'] === 'bank' ? 'selected' : '' ?>><?= e($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="label" for="refund_method">Method</label><select class="input" id="refund_method" name="refund_method"><option value="bank">Bank transfer</option><option value="cash">Cash</option><option value="card">Card reversal</option><option value="store_credit">Store credit</option></select></div>
        <div><label class="label" for="refund_ref">Reference</label><input class="input" id="refund_ref" name="refund_ref" maxlength="60"></div>
        <button class="btn btn-primary w-full" name="op" value="refund" type="submit"><?= icon('banknote', 'size-4') ?>Record refund</button>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </section>
</div>
