<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Services\Audit;

final class ProfileController
{
    public function index(): string
    {
        $auth = Auth::instance();
        $companies = $auth->isSuper() ? $auth->companies() : Database::all(
            "SELECT c.id, c.code, c.name, c.base_currency, c.tax_regime, uc.role
               FROM user_companies uc JOIN companies c ON c.id = uc.company_id
              WHERE uc.user_id = ? AND uc.is_active = 1 ORDER BY c.name",
            [$auth->id()]
        );
        return View::render('profile/index', [
            'title' => 'My Profile',
            'activeModule' => 'profile',
            'user' => $auth->user(),
            'companies' => $companies,
            'tab' => in_array($_GET['tab'] ?? '', ['security', 'companies', 'logins'], true) ? $_GET['tab'] : 'details',
        ]);
    }

    public function update(): never
    {
        $auth = Auth::instance();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $tz = (string) ($_POST['timezone'] ?? '');
        $default = (int) ($_POST['default_company_id'] ?? 0);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Name is required (max 120 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid e-mail address.';
        }
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Select a valid time zone.';
        }
        $companyIds = array_map(static fn ($c) => (int) $c['id'], $auth->companies());
        if ($default && !in_array($default, $companyIds, true)) {
            $errors['default_company_id'] = 'Select one of your companies.';
        }
        if ($errors) {
            json_out(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
        }
        $before = $auth->user();
        $data = ['name' => $name, 'email' => $email, 'phone' => mb_substr($phone, 0, 40) ?: null, 'timezone' => $tz, 'default_company_id' => $default ?: null];
        Database::update('users', $data, 'id = ?', [$auth->id()]);
        Audit::log('profile', $auth->id(), 'update', $before, $data);
        json_out(['ok' => true, 'message' => 'Profile updated.']);
    }

    public function password(): never
    {
        $auth = Auth::instance();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        if (!password_verify($current, $auth->user()['password_hash'])) {
            json_out(['ok' => false, 'message' => 'Current password is incorrect.', 'errors' => ['current_password' => 'Current password is incorrect.']], 422);
        }
        $error = Auth::passwordPolicyError($new);
        if (!$error && $new !== $confirm) {
            $error = 'The passwords do not match.';
        }
        if (!$error && hash_equals($current, $new)) {
            $error = 'The new password must be different from the current one.';
        }
        if ($error) {
            json_out(['ok' => false, 'message' => $error, 'errors' => ['password' => $error]], 422);
        }
        Database::update('users', ['password_hash' => Auth::hash($new), 'password_changed_at' => now_utc(), 'must_change_password' => 0], 'id = ?', [$auth->id()]);
        Session::regenerate();
        Audit::log('profile', $auth->id(), 'password_change');
        json_out(['ok' => true, 'message' => 'Password changed successfully.']);
    }

    public function switchCompany(): never
    {
        $id = (int) ($_POST['company_id'] ?? 0);
        $ok = Auth::instance()->switchCompany($id);
        if (is_ajax()) {
            json_out(['ok' => $ok, 'message' => $ok ? 'Company switched.' : 'You do not have access to that company.']);
        }
        if ($ok) {
            Session::flash('success', 'Switched to ' . (Auth::instance()->company()['name'] ?? 'company') . '.');
        }
        // Return to the same module root (ids from the previous company are not valid any more)
        $back = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?: '/dashboard';
        $back = preg_replace('#/(\d+)(/edit)?$#', '', substr($back, strlen(app_prefix())) ?: '/dashboard');
        redirect(str_contains((string) $back, '/new') ? '/accountant/journals' : ($back ?: '/dashboard'));
    }
}
