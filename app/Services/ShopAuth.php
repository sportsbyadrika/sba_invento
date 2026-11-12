<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\ValidationException;

/** Shop customer accounts (separate from staff users, separate session cookie). */
final class ShopAuth
{
    private const MAX_FAILS = 5;
    private const LOCK_MINUTES = 15;

    public static function customer(int $cid): ?array
    {
        $id = (int) ($_SESSION['shop_customer'][$cid] ?? 0);
        if (!$id) {
            return null;
        }
        $c = Database::one("SELECT * FROM shop_customers WHERE id = ? AND company_id = ? AND status = 'active'", [$id, $cid]);
        if (!$c) {
            unset($_SESSION['shop_customer'][$cid]);
        }
        return $c;
    }

    public static function normalisePhone(string $phone): string
    {
        return ltrim((string) preg_replace('/\D+/', '', $phone), '0');
    }

    public static function register(int $cid, array $in): int
    {
        $e = [];
        $name = trim((string) ($in['name'] ?? ''));
        $code = (string) ($in['phone_code'] ?? '');
        $phone = self::normalisePhone((string) ($in['phone'] ?? ''));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $pw = (string) ($in['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 120) {
            $e['name'] = 'Enter your name.';
        }
        if (!isset(Shop::PHONE_CODES[$code])) {
            $e['phone_code'] = 'Select the country code.';
        }
        if (strlen($phone) < 6 || strlen($phone) > 15) {
            $e['phone'] = 'Enter a valid mobile number.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Enter a valid e-mail address.';
        }
        if ($err = Auth::passwordPolicyError($pw)) {
            $e['password'] = $err;
        } elseif ($pw !== (string) ($in['password_confirmation'] ?? '')) {
            $e['password_confirmation'] = 'The passwords do not match.';
        }
        if (!$e && Database::value('SELECT id FROM shop_customers WHERE company_id = ? AND email = ?', [$cid, $email])) {
            $e['email'] = 'An account with this e-mail already exists. Please sign in.';
        }
        if (!$e && Database::value('SELECT id FROM shop_customers WHERE company_id = ? AND phone_code = ? AND phone = ?', [$cid, $code, $phone])) {
            $e['phone'] = 'An account with this mobile number already exists.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        $id = Database::insert('shop_customers', ['company_id' => $cid, 'name' => $name, 'phone_code' => $code, 'phone' => $phone, 'email' => $email, 'password_hash' => Auth::hash($pw)]);
        self::start($cid, $id);
        return $id;
    }

    public static function attempt(int $cid, string $login, string $password): void
    {
        $login = trim($login);
        $c = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? Database::one('SELECT * FROM shop_customers WHERE company_id = ? AND email = ?', [$cid, strtolower($login)])
            : Database::one("SELECT * FROM shop_customers WHERE company_id = ? AND phone = ?", [$cid, self::normalisePhone($login)]);
        $generic = 'Incorrect e-mail / mobile number or password.';
        if (!$c) {
            password_verify($password, Auth::hash('x'));
            throw new ValidationException([], $generic);
        }
        if ($c['status'] !== 'active') {
            throw new ValidationException([], 'This account is disabled. Please contact the store.');
        }
        if ($c['locked_until'] && strtotime($c['locked_until'] . ' UTC') > time()) {
            throw new ValidationException([], 'Too many failed attempts. Try again after ' . self::LOCK_MINUTES . ' minutes or reset your password.');
        }
        if (!password_verify($password, $c['password_hash'])) {
            $f = (int) $c['failed_attempts'] + 1;
            Database::update('shop_customers', $f >= self::MAX_FAILS ? ['failed_attempts' => 0, 'locked_until' => gmdate('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)] : ['failed_attempts' => $f], 'id = ?', [$c['id']]);
            throw new ValidationException([], $f >= self::MAX_FAILS ? 'Too many failed attempts. Your account is locked for ' . self::LOCK_MINUTES . ' minutes.' : $generic);
        }
        Database::update('shop_customers', ['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now_utc()], 'id = ?', [$c['id']]);
        self::start($cid, (int) $c['id']);
    }

    private static function start(int $cid, int $id): void
    {
        $cart = $_SESSION['cart'] ?? [];          // keep the guest cart
        Session::regenerate();
        $_SESSION['cart'] = $cart;
        $_SESSION['shop_customer'][$cid] = $id;
    }

    public static function logout(int $cid): void
    {
        unset($_SESSION['shop_customer'][$cid]);
        Session::regenerate();
    }

    public static function sendReset(int $cid, string $email, string $storeSlug, string $storeName): void
    {
        $c = Database::one("SELECT * FROM shop_customers WHERE company_id = ? AND email = ? AND status = 'active'", [$cid, strtolower(trim($email))]);
        if (!$c) {
            return; // never reveal whether an account exists
        }
        $token = bin2hex(random_bytes(32));
        Database::update('shop_customers', ['reset_token_hash' => hash('sha256', $token), 'reset_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)], 'id = ?', [$c['id']]);
        $link = rtrim((string) config('app.url'), '/') . url("/store/$storeSlug/reset/$token");
        Mailer::send($c['email'], "$storeName: reset your password", '<p>Hello ' . e($c['name']) . ',</p><p><a href="' . e($link) . '">Choose a new password</a> (valid for 60 minutes).</p><p>If you did not ask for this, ignore this e-mail.</p>');
    }

    public static function resetCustomer(int $cid, string $token): ?array
    {
        return Database::one('SELECT * FROM shop_customers WHERE company_id = ? AND reset_token_hash = ? AND reset_expires_at > ?', [$cid, hash('sha256', $token), now_utc()]);
    }

    public static function resetPassword(array $c, string $pw, string $confirm): void
    {
        if ($err = Auth::passwordPolicyError($pw)) {
            throw new ValidationException(['password' => $err]);
        }
        if ($pw !== $confirm) {
            throw new ValidationException(['password_confirmation' => 'The passwords do not match.']);
        }
        Database::update('shop_customers', ['password_hash' => Auth::hash($pw), 'reset_token_hash' => null, 'reset_expires_at' => null, 'failed_attempts' => 0, 'locked_until' => null], 'id = ?', [$c['id']]);
    }
}
