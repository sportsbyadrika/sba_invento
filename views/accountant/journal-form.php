<?php
$company = auth()->company();
$j = $journal ?? null;
$lines = array_map(static fn ($l) => [
    'account_id' => (int) $l['account_id'], 'account_label' => $l['account_code'] . ' · ' . $l['account_name'],
    'description' => (string) $l['description'], 'debit' => (float) $l['debit'], 'credit' => (float) $l['credit'],
], $j['lines'] ?? []);
$config = [
    'lines' => $lines,
    'base' => $company['base_currency'],
    'currencies' => array_map(static fn ($c) => ['code' => $c['code'], 'rate' => (float) $c['exchange_rate'], 'decimals' => (int) $c['decimal_places'], 'base' => (bool) $c['is_base']], $currencies),
    'searchUrl' => url('/accountant/accounts/search'),
];
$currency = $j['currency_code'] ?? $company['base_currency'];
?>
<form method="post" action="<?= e(url('/accountant/journals/store')) ?>" data-journal autocomplete="off" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($j['id'] ?? '') ?>">
  <input type="hidden" name="submit_action" value="draft" data-submit-action>
  <input type="hidden" name="after" value="" data-after>
  <script type="application/json" data-journal-config><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?>Accountant<?= icon('chevron-right', 'size-3') ?><a href="<?= e(url('/accountant/journals')) ?>">Manual Journals</a></nav>
      <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
        <?= icon('book-open', 'size-6 text-cyan-700') ?><?= e(!empty($j['id']) ? 'Edit Journal' : 'New Journal') ?>
        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-sm text-slate-600"><?= e($j['number'] ?? $nextNumber) ?></span>
        <span class="badge badge-amber">Draft</span>
      </h1>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= e(url('/accountant/journals')) ?>" class="btn">Cancel</a>
      <button type="submit" class="btn" data-submit="draft" title="Ctrl+S"><?= icon('save', 'size-4') ?>Save draft <span class="kbd">Ctrl S</span></button>
      <button type="submit" class="btn" data-submit="post" data-after-new title="Post and start another">Post & New</button>
      <button type="submit" class="btn btn-success" data-submit="post" title="Alt+P"><?= icon('circle-check', 'size-4') ?>Save & Post <span class="kbd border-emerald-500 bg-emerald-500 text-emerald-50">Alt P</span></button>
    </div>
  </div>

  <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>

  <section class="card p-4" data-enter-nav>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
      <div>
        <label class="label" for="journal_date">Date <span class="text-rose-500">*</span></label>
        <input type="date" class="input" id="journal_date" name="journal_date" value="<?= e($j['journal_date'] ?? date('Y-m-d')) ?>" required autofocus>
        <p class="field-error hidden" data-error-for="journal_date"></p>
      </div>
      <div>
        <label class="label" for="reference">Reference</label>
        <input type="text" class="input" id="reference" name="reference" maxlength="60" value="<?= e($j['reference'] ?? '') ?>" placeholder="e.g. Bank ref / bill no.">
      </div>
      <div>
        <label class="label" for="currency_code">Currency</label>
        <select class="input" id="currency_code" name="currency_code" data-currency>
          <?php foreach ($currencies as $c): ?><option value="<?= e($c['code']) ?>" <?= $c['code'] === $currency ? 'selected' : '' ?>><?= e($c['code']) ?><?= $c['is_base'] ? ' (base)' : '' ?></option><?php endforeach; ?>
        </select>
        <p class="field-error hidden" data-error-for="currency_code"></p>
      </div>
      <div data-rate-wrap class="<?= $currency === $company['base_currency'] ? 'hidden' : '' ?>">
        <label class="label" for="exchange_rate">Rate (1 <span data-cur-label><?= e($currency) ?></span> = ? <?= e($company['base_currency']) ?>)</label>
        <input type="text" inputmode="decimal" class="input text-right tabular-nums" id="exchange_rate" name="exchange_rate" value="<?= e(isset($j['exchange_rate']) ? rtrim(rtrim((string) $j['exchange_rate'], '0'), '.') : '1') ?>" data-rate>
        <p class="field-error hidden" data-error-for="exchange_rate"></p>
      </div>
      <div class="sm:col-span-2 lg:col-span-2" data-narration-wrap>
        <label class="label" for="narration">Narration</label>
        <input type="text" class="input" id="narration" name="narration" maxlength="500" value="<?= e($j['narration'] ?? '') ?>" placeholder="Being …">
      </div>
    </div>
  </section>

  <section class="card mt-4 overflow-visible">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2">
      <h2 class="text-sm font-bold text-slate-900">Entries</h2>
      <span class="text-xs text-slate-500"><span class="kbd">Enter</span> next cell · a blank account ends entry · <span class="kbd">Alt A</span> add line · <span class="kbd">Alt B</span> balance · <span class="kbd">Ctrl Del</span> remove line</span>
    </div>
    <div class="overflow-x-auto">
      <table class="erp-table" data-lines-table>
        <thead>
          <tr>
            <th class="w-10">#</th>
            <th class="min-w-72">Account</th>
            <th class="min-w-56">Line description</th>
            <th class="w-40 text-right">Debit (<span data-cur-label><?= e($currency) ?></span>)</th>
            <th class="w-40 text-right">Credit (<span data-cur-label><?= e($currency) ?></span>)</th>
            <th class="w-10"></th>
          </tr>
        </thead>
        <tbody data-lines></tbody>
        <tfoot>
          <tr class="bg-slate-50 text-sm font-bold">
            <td colspan="2" class="px-3 py-2"><button type="button" class="btn btn-sm" data-add-line><?= icon('plus', 'size-3.5') ?>Add line</button></td>
            <td class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Total</td>
            <td class="px-3 py-2 text-right tabular-nums" data-total-dr>0.00</td>
            <td class="px-3 py-2 text-right tabular-nums" data-total-cr>0.00</td>
            <td></td>
          </tr>
          <tr>
            <td colspan="3" class="px-3 py-2 text-right text-xs text-slate-500" data-base-note></td>
            <td colspan="2" class="px-3 py-2 text-right text-sm" data-diff></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="field-error hidden px-4 pb-3" data-error-for="lines"></p>
  </section>
</form>

<template data-line-template>
  <tr data-line>
    <td class="font-mono text-xs text-slate-400" data-line-no></td>
    <td class="relative">
      <input type="hidden" data-f="account_id">
      <input type="text" class="input" placeholder="Type code or name…" data-account-input aria-label="Account" role="combobox" aria-autocomplete="list" aria-expanded="false">
      <ul class="absolute top-full right-3 left-3 z-30 mt-0.5 hidden max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl" data-account-list role="listbox"></ul>
      <p class="field-error hidden" data-line-error="account_id"></p>
    </td>
    <td><input type="text" class="input" maxlength="255" data-f="description" aria-label="Description"></td>
    <td><input type="text" inputmode="decimal" class="input text-right tabular-nums" data-f="debit" aria-label="Debit"><p class="field-error hidden" data-line-error="debit"></p></td>
    <td><input type="text" inputmode="decimal" class="input text-right tabular-nums" data-f="credit" aria-label="Credit"></td>
    <td><button type="button" class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-remove-line tabindex="-1" aria-label="Remove line"><?= icon('trash-2', 'size-4') ?></button></td>
  </tr>
</template>
