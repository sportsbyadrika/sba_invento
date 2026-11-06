<?php
use App\Services\TaxRules;
$c = $company;
$months = array_combine(range(1, 12), array_map(static fn ($m) => date('F', mktime(0, 0, 0, $m, 1)), range(1, 12)));
?>
<div class="mb-4">
  <nav class="flex items-center gap-1 text-xs text-slate-500"><a href="<?= e(url('/dashboard')) ?>">Home</a><?= icon('chevron-right', 'size-3') ?>Settings<?= icon('chevron-right', 'size-3') ?><span class="font-semibold text-slate-700">Company Profile</span></nav>
  <h1 class="mt-1 flex items-center gap-2 text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl"><?= icon('building-2', 'size-6 text-cyan-700') ?>Company Profile</h1>
</div>
<form class="card max-w-4xl p-5" method="post" action="<?= e(url('/settings/company')) ?>" data-ajax-form data-enter-nav novalidate>
  <?= csrf_field() ?>
  <div class="mb-3 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" data-form-error></div>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div><label class="label">Company code</label><input class="input font-mono" value="<?= e($c['code']) ?>" disabled></div>
    <div class="lg:col-span-2"><label class="label" for="name">Company name</label><input class="input" id="name" name="name" value="<?= e($c['name']) ?>" required maxlength="150"><p class="field-error hidden" data-error-for="name"></p></div>
    <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="legal_name">Legal name (as on tax registration)</label><input class="input" id="legal_name" name="legal_name" value="<?= e($c['legal_name']) ?>" maxlength="200"></div>
    <div class="pt-2 sm:col-span-2 lg:col-span-3"><div class="section-title border-b border-slate-100 pb-1.5">Tax</div></div>
    <div>
      <label class="label" for="tax_regime">Tax regime</label>
      <select class="input" id="tax_regime" name="tax_regime" <?= $hasTransactions ? 'disabled' : '' ?>>
        <?php foreach (['NONE' => 'No tax', 'UAE_VAT' => 'UAE VAT', 'IN_GST' => 'India GST'] as $k => $l): ?><option value="<?= $k ?>" <?= $c['tax_regime'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select>
      <?php if ($hasTransactions): ?><input type="hidden" name="tax_regime" value="<?= e($c['tax_regime']) ?>"><p class="help">Locked because transactions exist.</p><?php endif; ?>
    </div>
    <div><label class="label" for="tax_number"><?= $c['tax_regime'] === 'IN_GST' ? 'GSTIN' : 'TRN' ?></label><input class="input font-mono uppercase" id="tax_number" name="tax_number" value="<?= e($c['tax_number']) ?>" maxlength="30"><p class="field-error hidden" data-error-for="tax_number"></p></div>
    <div>
      <label class="label" for="state_code"><?= $c['tax_regime'] === 'IN_GST' ? 'State (GST state code)' : 'Emirate' ?></label>
      <select class="input" id="state_code" name="state_code">
        <option value="">—</option>
        <?php foreach (TaxRules::places($c['tax_regime']) as $k => $l): ?><option value="<?= e($k) ?>" <?= (string) $c['state_code'] === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="pt-2 sm:col-span-2 lg:col-span-3"><div class="section-title border-b border-slate-100 pb-1.5">Accounting & regional</div></div>
    <div><label class="label">Base currency</label><input class="input font-mono" value="<?= e($c['base_currency']) ?>" disabled><p class="help">Fixed at company creation.</p></div>
    <div><label class="label" for="fiscal_year_start">Financial year starts</label><select class="input" id="fiscal_year_start" name="fiscal_year_start"><?php foreach ($months as $n => $m): ?><option value="<?= $n ?>" <?= (int) $c['fiscal_year_start'] === $n ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="date_format">Date format</label><select class="input" id="date_format" name="date_format"><?php foreach (['d-m-Y' => '31-12-2026', 'd/m/Y' => '31/12/2026', 'd-M-Y' => '31-Dec-2026', 'm/d/Y' => '12/31/2026', 'Y-m-d' => '2026-12-31'] as $k => $l): ?><option value="<?= $k ?>" <?= $c['date_format'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="timezone">Company time zone</label><select class="input" id="timezone" name="timezone" data-searchable><?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option value="<?= e($tz) ?>" <?= $c['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div>
    <div class="pt-2 sm:col-span-2 lg:col-span-3"><div class="section-title border-b border-slate-100 pb-1.5">Contact</div></div>
    <div><label class="label" for="email">E-mail</label><input class="input" id="email" name="email" type="email" value="<?= e($c['email']) ?>" maxlength="150"><p class="field-error hidden" data-error-for="email"></p></div>
    <div><label class="label" for="phone">Phone</label><input class="input" id="phone" name="phone" value="<?= e($c['phone']) ?>" maxlength="40"></div>
    <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="address">Address</label><textarea class="input" id="address" name="address" rows="3" maxlength="1000"><?= e($c['address']) ?></textarea></div>
  </div>
  <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?>Save company profile</button></div>
</form>
