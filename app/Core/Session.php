<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || config('app.force_https');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        if (PHP_VERSION_ID < 80400) { // deprecated in PHP 8.4 (its defaults are already strong)
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
        }
        // The public online shop (/store/...) uses its own cookie: customers and staff never share a session.
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $isStore = (bool) preg_match('#^' . preg_quote(base_path_url(), '#') . '(/index\.php)?/store(/|$)#', $path);
        session_name($isStore ? 'sba_store' : (string) config('security.session_name', 'sba_sid'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path_url() ?: '/',
            'secure'   => (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        $m = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $m;
    }
}
