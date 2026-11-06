<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\Audit;
use App\Services\TaxRules;

final class CompanySettingsController
{
    public function edit(): string
    {
        return View::render('settings/company', [
            'title' => 'Company Profile',
            'activeModule' => 'settings',
            'company' => Auth::instance()->company(),
            'currencies' => Database::all('SELECT code, name FROM currencies WHERE company_id = ? ORDER BY code', [Auth::instance()->companyId()]),
            'hasTransactions' => (bool) Database::value('SELECT COUNT(*) FROM journals WHERE company_id = ?', [Auth::instance()->companyId()]),
        ]);
    }

    public function update(): never
    {
        $auth = Auth::instance();
        $company = $auth->company();
        $in = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $_POST);
        $errors = [];
        if (($in['name'] ?? '') === '' || mb_strlen($in['name']) > 150) {
            $errors['name'] = 'Company name is required.';
        }
        if (!empty($in['email']) && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid e-mail address.';
        }
        if (!in_array($in['timezone'] ?? '', \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Select a valid time zone.';
        }
        $regime = $in['tax_regime'] ?? 'NONE';
        if (!in_array($regime, ['NONE', 'UAE_VAT', 'IN_GST'], true)) {
            $errors['tax_regime'] = 'Select a tax regime.';
        }
        if ($err = TaxRules::validateTaxNumber($regime, (string) ($in['tax_number'] ?? ''), (string) ($in['state_code'] ?? ''))) {
            $errors['tax_number'] = $err;
        }
        $fy = (int) ($in['fiscal_year_start'] ?? 1);
        if ($fy < 1 || $fy > 12) {
            $errors['fiscal_year_start'] = 'Select a month.';
        }
        if (!in_array($in['date_format'] ?? '', ['d-m-Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd-M-Y'], true)) {
            $errors['date_format'] = 'Select a date format.';
        }
        if ($errors) {
            json_out(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
        }
        $data = [
            'name' => $in['name'],
            'legal_name' => mb_substr((string) ($in['legal_name'] ?? ''), 0, 200) ?: null,
            'tax_regime' => $regime,
            'tax_number' => strtoupper((string) ($in['tax_number'] ?? '')) ?: null,
            'state_code' => mb_substr((string) ($in['state_code'] ?? ''), 0, 10) ?: null,
            'fiscal_year_start' => $fy,
            'timezone' => $in['timezone'],
            'date_format' => $in['date_format'],
            'email' => $in['email'] ?: null,
            'phone' => mb_substr((string) ($in['phone'] ?? ''), 0, 40) ?: null,
            'address' => mb_substr((string) ($in['address'] ?? ''), 0, 1000) ?: null,
        ];
        Database::update('companies', $data, 'id = ?', [$company['id']]);
        Audit::log('company', $company['id'], 'update', $company, $data);
        json_out(['ok' => true, 'message' => 'Company profile saved.']);
    }
}
