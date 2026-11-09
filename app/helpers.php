<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** HTML escape */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path_url(): string
{
    static $base = null;
    if ($base === null) {
        $base = (string) config('app.base_path', '');
        if ($base === '' && PHP_SAPI !== 'cli') {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $dir = preg_replace('#/public$#', '', $dir);
            $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
        }
    }
    return $base;
}

/**
 * Prefix for application URLs. Normally the same as base_path_url(). When the server does not
 * apply the .htaccess rewrite rules, the app is reached as /index.php/... and every generated
 * link keeps that prefix, so the whole application still works (just with uglier URLs).
 */
function app_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        $prefix = base_path_url();
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
            $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $front = base_path_url() . '/index.php';
            $mode = config('app.pretty_urls', 'auto'); // 'auto' | true | false
            // public/.htaccess sets SBA_REWRITE, so its presence proves the rewrite rules are active
            $rewrite = isset($_SERVER['SBA_REWRITE']) || isset($_SERVER['REDIRECT_SBA_REWRITE'])
                || stripos((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''), 'nginx') !== false;
            if ($path === $front || str_starts_with($path, $front . '/') || $mode === false || ($mode === 'auto' && !$rewrite)) {
                $prefix = $front;
            }
        }
    }
    return $prefix;
}

/** Build an application URL (never with .php, unless the server cannot rewrite) */
function url(string $path = '/', array $query = []): string
{
    $u = app_prefix() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '1';
    return base_path_url() . '/assets/' . ltrim($path, '/') . '?v=' . $v; // real file, never via index.php
}

/** Inline Lucide SVG icon (self hosted - no CDN, no JS needed) */
function icon(string $name, string $class = 'size-4'): string
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $file = BASE_PATH . '/app/icons/' . basename($name) . '.svg';
        $cache[$name] = is_file($file) ? (string) file_get_contents($file) : '';
    }
    if ($cache[$name] === '') {
        return '';
    }
    return preg_replace('/<svg\b/', '<svg aria-hidden="true" class="' . e($class) . '"', $cache[$name], 1) ?? '';
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function auth(): Auth
{
    return Auth::instance();
}

function can(string $module, string $action = 'view'): bool
{
    return Auth::instance()->can($module, $action);
}

function flash(?string $key = null, ?string $message = null): mixed
{
    if ($message !== null) {
        Session::flash($key ?? 'info', $message);
        return null;
    }
    return Session::getFlash($key ?? 'info');
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old', []);
    return $old[$key] ?? $default;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = (array) config('security.trusted_proxies', []);
    if ($trusted && in_array($ip, $trusted, true)) {
        $fwd = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($fwd !== '') {
            $candidate = trim(explode(',', $fwd)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
    }
    return $ip;
}

function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** The timezone used for display (user > company > UTC) */
function display_tz(): string
{
    $a = Auth::instance();
    return $a->user()['timezone'] ?? ($a->company()['timezone'] ?? 'UTC') ?: 'UTC';
}

/** Convert a UTC datetime string from the DB to the viewer's local time */
function local_dt(?string $utc, string $format = 'd-M-Y H:i'): string
{
    if (!$utc) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone(display_tz()))->format($format);
    } catch (Throwable) {
        return (string) $utc;
    }
}

function fmt_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $fmt = Auth::instance()->company()['date_format'] ?? 'd-m-Y';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : $date;
}

function base_decimals(): int
{
    return (int) (Auth::instance()->company()['_decimals'] ?? 2);
}

function money(mixed $amount, ?int $decimals = null): string
{
    $decimals ??= base_decimals();
    return number_format((float) $amount, $decimals, '.', ',');
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function view(string $name, array $data = [], ?string $layout = 'app'): string
{
    return App\Core\View::render($name, $data, $layout);
}

function str_slug(string $s): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}
