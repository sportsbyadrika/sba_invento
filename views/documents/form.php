<?php
/** Quote / invoice / purchase order entry. @var string $type @var array $cfg @var ?array $doc */
$company = auth()->company();
$d = $doc ?? null;
$isSale = $cfg['contact'] === 'customer';
$lines = array_map(static fn ($l) => [
    'item_id' => $l['item_id'] ? (int) $l['item_id'] : null, 'item_label' => $l['item_id'] ? ($l['sku'] . ' · ' . $l['item_name']) : '',
    'description' => (string) $l['description'], 'qty' => (float) $l['qty'], 'unit_price' => (float) $l['unit_price'],
    'discount_pct' => (float) $l['discount_pct'], 'tax_rate_id' => $l['tax_rate_id'] ? (int) $l['tax_rate_id'] : null,
], $d['lines'] ?? []);
$defaultTax = null;
foreach ($taxes as $t) {
    if ($t['is_default']) {
        $defaultTax = (int) $t['id'];
    }
}
$config = [
    'lines' => $lines, 'base' => $company['base_currency'], 'kind' => $isSale ? 'sale' : 'purchase', 'type' => $type,
    'taxes' => array_map(static fn ($t) => ['id' => (int) $t['id'], 'name' => $t['name'], 'rate' => (float) $t['rate']], $taxes),
    'defaultTax' => $defaultTax,
    'currencies' => array_map(static fn ($c) => ['code' => $c['code'], 'rate' => (float) $c['exchange_rate'], 'decimals' => (int) $c['decimal_places'], 'base' => (bool) $c['is_base']], $currencies),
    'contacts' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'currency' => $c['currency_code'], 'pos' => $c['place_of_supply'], 'terms' => (int) $c['payment_terms'], 'bill' => $c['billing_address'], 'ship' => $c['shipping_address']], $contacts),
    'searchUrl' => url('/inventory/items/search'),
];
$currency = $d['currency_code'] ?? $company['base_currency'];
$postLabel = ['invoice' => 'Save & Post', 'po' => 'Save & Approve', 'quote' => 'Save & Mark sent'][$type];
$freightLabel = $isSale ? 'Delivery charge' : 'Freight / landed cost';
?>
<form method="post" action="<?= e(url('/' . $cfg['path'] . '/store')) ?>" data-document autocomplete="off" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($d['id'] ?? '') ?>">
  <input type="hidden" name="submit_action" value="draft" data-submit-action>
  <input type="hidden" name="after" value="" data-after>
  <script type="application/json" data-document-config><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?><?= e($isSale ? 'Sales' : 'Purchases') ?><?= icon('chevron-right', 'size-3') ?><a href="<?= e(url('/' . $cfg['path'])) ?>"><?= e($cfg['plural']) ?></a></nav>
      <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
        <?= e(!empty($d['id']) ? 'Edit ' . $cfg['label'] : 'New ' . $cfg['label']) ?>
        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-sm text-slate-600"><?= e($d['number'] ?? $nextNumber) ?></span>
        <span class="badge badge-amber">Draft</span>
      </h1>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= e(url('/' . $cfg['path'])) ?>" class="btn">Cancel</a>
      <button type="submit" class="btn" data-submit="draft" title="Ctrl+S"><?= icon('save', 'size-4') ?>Save draft <span class="kbd">Ctrl S</span></button>
      <button type="submit" class="btn" data-submit="post" data-after-new><?= e(str_replace('Save & ', '', $postLabel)) ?> & New</button>
      <button type="submit" class="btn btn-success" data-submit="post" title="Alt+P"><?= icon('circle-check', 'size-4') ?><?= e($postLabel) ?> <span class="kbd border-emerald-500 bg-emerald-500 text-emerald-50">Alt P</span></button>
    </div>
  </div>

  <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>

  <section class="card p-4" data-enter-nav>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
      <div class="sm:col-span-2">
        <label class="label" for="contact_id"><?= $isSale ? 'Customer' : 'Vendor' ?> <span class="text-rose-500">*</span></label>
        <select class="input" id="contact_id" name="contact_id" required autofocus data-contact>
          <option value="">— Select <?= $isSale ? 'customer' : 'vendor' ?> —</option>
          <?php foreach ($contacts as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($d['contact_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option><?php endforeach; ?>
        </select>
        <p class="field-error hidden" data-error-for="contact_id"></p>
      </div>
      <div>
        <label class="label" for="doc_date">Date <span class="text-rose-500">*</span></label>
        <input type="date" class="input" id="doc_date" name="doc_date" value="<?= e($d['doc_date'] ?? date('Y-m-d')) ?>" required>
        <p class="field-error hidden" data-error-for="doc_date"></p>
      </div>
      <div>
        <label class="label" for="due_date"><?= e($cfg['due']) ?></label>
        <input type="date" class="input" id="due_date" name="due_date" value="<?= e($d['due_date'] ?? '') ?>" data-due>
      </div>
      <div>
        <label class="label" for="reference">Reference</label>
        <input type="text" class="input" id="reference" name="reference" maxlength="60" value="<?= e($d['reference'] ?? '') ?>" placeholder="<?= $isSale ? 'Customer PO no.' : 'Vendor quote no.' ?>">
      </div>
      <div>
        <label class="label" for="currency_code">Currency</label>
        <select class="input" id="currency_code" name="currency_code" data-currency>
          <?php foreach ($currencies as $c): ?><option value="<?= e($c['code']) ?>" <?= $c['code'] === $currency ? 'selected' : '' ?>><?= e($c['code']) ?><?= $c['is_base'] ? ' (base)' : '' ?></option><?php endforeach; ?>
        </select>
      </div>
      <div data-rate-wrap class="<?= $currency === $company['base_currency'] ? 'hidden' : '' ?>">
        <label class="label" for="exchange_rate">Rate (1 <span data-cur-label><?= e($currency) ?></span> = ? <?= e($company['base_currency']) ?>)</label>
        <input type="text" inputmode="decimal" class="input text-right tabular-nums" id="exchange_rate" name="exchange_rate" value="<?= e(isset($d['exchange_rate']) ? rtrim(rtrim((string) $d['exchange_rate'], '0'), '.') : '1') ?>" data-rate>
      </div>
      <?php if ($places): ?>
      <div>
        <label class="label" for="place_of_supply"><?= $company['tax_regime'] === 'IN_GST' ? 'Place of supply' : 'Emirate' ?></label>
        <select class="input" id="place_of_supply" name="place_of_supply" data-pos>
          <option value="">—</option>
          <?php foreach ($places as $k => $l): ?><option value="<?= e($k) ?>" <?= (string) ($d['place_of_supply'] ?? '') === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card mt-4 overflow-visible">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2">
      <h2 class="text-sm font-bold text-slate-900">Items</h2>
      <span class="hidden text-xs text-slate-500 md:inline"><span class="kbd">Enter</span> next cell · blank item ends entry · <span class="kbd">Alt A</span> add line · <span class="kbd">Ctrl Del</span> remove line</span>
    </div>
    <div class="overflow-x-auto">
      <table class="erp-table" data-lines-table>
        <thead>
          <tr>
            <th class="w-8">#</th>
            <th class="min-w-64">Item</th>
            <th class="min-w-48">Description</th>
            <th class="w-24 text-right">Qty</th>
            <th class="w-32 text-right"><?= $isSale ? 'Rate' : 'Cost' ?></th>
            <th class="w-20 text-right">Disc %</th>
            <th class="w-40">Tax</th>
            <th class="w-32 text-right">Amount</th>
            <th class="w-8"></th>
          </tr>
        </thead>
        <tbody data-lines></tbody>
      </table>
    </div>
    <div class="flex flex-wrap items-start justify-between gap-4 border-t border-slate-200 p-4">
      <div class="w-full max-w-lg space-y-3" data-enter-nav>
        <button type="button" class="btn btn-sm" data-add-line><?= icon('plus', 'size-3.5') ?>Add line</button>
        <div><label class="label" for="notes">Notes (printed)</label><textarea class="input" id="notes" name="notes" rows="2" maxlength="1000"><?= e($d['notes'] ?? '') ?></textarea></div>
        <div><label class="label" for="terms">Terms & conditions</label><textarea class="input" id="terms" name="terms" rows="2" maxlength="1000"><?= e($d['terms'] ?? '') ?></textarea></div>
      </div>
      <dl class="w-full max-w-sm space-y-1.5 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Sub total</dt><dd class="tabular-nums font-semibold" data-t-sub>0.00</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular-nums text-slate-600" data-t-disc>0.00</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular-nums" data-t-tax>0.00</dd></div>
        <div class="flex items-center justify-between gap-3">
          <dt class="text-slate-500"><label for="freight"><?= e($freightLabel) ?></label><?php if (!$isSale): ?><span class="block text-[11px] text-slate-400">Added to stock cost when goods are received</span><?php endif; ?></dt>
          <dd><input type="text" inputmode="decimal" id="freight" name="freight" class="input w-32 text-right tabular-nums" value="<?= e(isset($d['freight']) && (float) $d['freight'] ? rtrim(rtrim((string) $d['freight'], '0'), '.') : '') ?>" placeholder="0.00" data-freight></dd>
        </div>
        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-extrabold text-slate-900"><dt>Total (<span data-cur-label><?= e($currency) ?></span>)</dt><dd class="tabular-nums" data-t-total>0.00</dd></div>
        <div class="text-right text-xs text-slate-500" data-base-note></div>
      </dl>
    </div>
    <p class="field-error hidden px-4 pb-3" data-error-for="lines"></p>
  </section>
</form>

<template data-line-template>
  <tr data-line>
    <td class="font-mono text-xs text-slate-400" data-line-no></td>
    <td class="relative">
      <input type="hidden" data-f="item_id">
      <input type="text" class="input" placeholder="SKU, name or barcode…" data-item-input aria-label="Item" role="combobox" aria-autocomplete="list" aria-expanded="false">
      <ul class="absolute top-full right-3 left-3 z-30 mt-0.5 hidden max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl" data-item-list role="listbox"></ul>
      <p class="field-error hidden" data-line-error="item_id"></p>
    </td>
    <td><input type="text" class="input" maxlength="255" data-f="description" aria-label="Description"></td>
    <td><input type="text" inputmode="decimal" class="input text-right tabular-nums" data-f="qty" aria-label="Quantity"><p class="field-error hidden" data-line-error="qty"></p></td>
    <td><input type="text" inputmode="decimal" class="input text-right tabular-nums" data-f="unit_price" aria-label="Rate"></td>
    <td><input type="text" inputmode="decimal" class="input text-right tabular-nums" data-f="discount_pct" aria-label="Discount %"></td>
    <td><select class="input" data-f="tax_rate_id" aria-label="Tax"></select></td>
    <td class="text-right tabular-nums font-medium" data-amount>0.00</td>
    <td><button type="button" class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-remove-line tabindex="-1" aria-label="Remove line"><?= icon('trash-2', 'size-4') ?></button></td>
  </tr>
</template>
