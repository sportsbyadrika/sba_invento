<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\CompanySettingsController;
use App\Controllers\DashboardController;
use App\Controllers\JournalController;
use App\Controllers\AccountController;
use App\Controllers\PlannedController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\ResourceController;

final class App
{
    public static function run(): void
    {
        self::securityHeaders();
        try {
            // New database changes after a deploy are applied automatically (once)
            if (\App\Services\Migrator::needsCheck() && is_file(BASE_PATH . '/storage/installed.lock')) {
                try {
                    \App\Services\Migrator::run();
                } catch (\Throwable $e) {
                    error_log('Migration failed: ' . $e->getMessage());
                    throw new HttpException(500, 'A database update could not be applied: ' . $e->getMessage());
                }
            }
            Session::start();
            Auth::instance()->boot();
            // Ajax GETs (list data, forms, reports) only read the session: release the session
            // lock at once so parallel requests from the same browser do not wait for each other.
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && is_ajax()) {
                session_write_close();
            }

            $router = new Router();
            self::routes($router);

            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $base = base_path_url();
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base)) ?: '/';
            }
            // Fallback when rewrites are not active: /index.php/install -> /install
            if (preg_match('#^/index\.php(/.*)?$#', $path, $m)) {
                $path = ($m[1] ?? '') ?: '/';
            }
            // Never expose .php in URLs: /login.php -> /login
            if (preg_match('#^(.+)\.php$#', $path, $m) && $m[1] !== '/index') {
                redirect($m[1]);
            }
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $match = $router->match($method, $path);
            if ($match === null) {
                throw new HttpException(404);
            }
            [$handler, $params, $middleware] = $match;
            if ($method === 'POST') {
                Csrf::verify();
            }
            foreach ($middleware as $mw) {
                self::middleware($mw);
            }
            [$class, $action] = $handler;
            $output = (new $class())->$action(...array_values($params));
            if (is_string($output)) {
                perf_headers();
                header('Content-Type: text/html; charset=utf-8');
                echo $output;
            }
        } catch (HttpException $e) {
            self::renderError($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            error_log((string) $e);
            $msg = config('app.debug') ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : 'An unexpected error occurred. The error has been logged.';
            self::renderError(500, $msg);
        }
    }

    private static function routes(Router $r): void
    {
        $auth = ['auth'];
        $r->get('/', [DashboardController::class, 'home']);
        $r->get('/login', [AuthController::class, 'showLogin'], ['guest']);
        $r->post('/login', [AuthController::class, 'login'], ['guest']);
        $r->post('/logout', [AuthController::class, 'logout']);
        $r->get('/forgot-password', [AuthController::class, 'showForgot'], ['guest']);
        $r->post('/forgot-password', [AuthController::class, 'forgot'], ['guest']);
        $r->get('/reset-password/{token:[a-f0-9]+}', [AuthController::class, 'showReset']);
        $r->post('/reset-password/{token:[a-f0-9]+}', [AuthController::class, 'reset']);

        $r->get('/admin/system', [\App\Controllers\SystemController::class, 'index'], ['auth', 'super']);
        $r->get('/install', [\App\Controllers\InstallController::class, 'show']);
        $r->post('/install', [\App\Controllers\InstallController::class, 'run']);

        // Public online shop (customer accounts, separate session cookie)
        $S = \App\Controllers\StoreController::class;
        $st = '/store/{shop:[a-z0-9][a-z0-9\-]*}';
        $r->get($st, [$S, 'index']);
        $r->get("$st/item/{item:\d+[a-z0-9\-]*}", [$S, 'product']);
        $r->post("$st/cart/add", [$S, 'cartAdd']);
        $r->get("$st/cart", [$S, 'cart']);
        $r->post("$st/cart/update", [$S, 'cartUpdate']);
        $r->get("$st/checkout", [$S, 'checkout']);
        $r->post("$st/checkout", [$S, 'placeOrder']);
        $r->get("$st/login", [$S, 'loginForm']);
        $r->post("$st/login", [$S, 'login']);
        $r->get("$st/register", [$S, 'registerForm']);
        $r->post("$st/register", [$S, 'register']);
        $r->post("$st/logout", [$S, 'logout']);
        $r->get("$st/forgot", [$S, 'forgotForm']);
        $r->post("$st/forgot", [$S, 'forgot']);
        $r->get("$st/reset/{token:[a-f0-9]+}", [$S, 'resetForm']);
        $r->post("$st/reset/{token:[a-f0-9]+}", [$S, 'reset']);
        $r->get("$st/account", [$S, 'account']);
        $r->get("$st/account/addresses", [$S, 'addresses']);
        $r->post("$st/account/addresses", [$S, 'addressSave']);
        $r->post("$st/account/addresses/delete/{id:\d+}", [$S, 'addressDelete']);
        $num = '{number:[A-Za-z0-9\-]+}';
        $r->get("$st/account/orders/$num/invoice", [$S, 'invoice']);
        $r->get("$st/account/orders/$num", [$S, 'order']);
        $r->post("$st/account/orders/$num/cancel", [$S, 'cancel']);
        $r->post("$st/account/orders/$num/return", [$S, 'returnRequest']);
        $r->post("$st/account/orders/$num/review", [$S, 'review']);
        $r->get("$st/page/{page:terms|privacy|returns|shipping}", [$S, 'page']);

        // Online shop - staff screens
        $H = \App\Controllers\ShopAdminController::class;
        $r->get('/shop/orders/{id:\d+}', [$H, 'order'], $auth);
        $r->post('/shop/orders/{id:\d+}/advance', [$H, 'advance'], $auth);
        $r->get('/shop/orders/{id:\d+}/packing-slip', [$H, 'packingSlip'], $auth);
        $r->get('/shop/returns/{id:\d+}', [$H, 'returnShow'], $auth);
        $r->post('/shop/returns/{id:\d+}/process', [$H, 'returnProcess'], $auth);
        $r->get('/shop/settings', [$H, 'settings'], ['auth', 'admin']);
        $r->post('/shop/settings', [$H, 'saveSettings'], ['auth', 'admin']);
        $r->get('/inventory/items/{id:\d+}/images', [$H, 'images'], $auth);
        $r->post('/inventory/items/{id:\d+}/images', [$H, 'uploadImages'], $auth);
        $r->post('/inventory/items/{id:\d+}/images/{img:\d+}/{op:main|delete}', [$H, 'imageOp'], $auth);

        $r->get('/dashboard', [DashboardController::class, 'index'], $auth);
        $r->post('/switch-company', [ProfileController::class, 'switchCompany'], $auth);
        $r->get('/profile', [ProfileController::class, 'index'], $auth);
        $r->post('/profile', [ProfileController::class, 'update'], $auth);
        $r->post('/profile/password', [ProfileController::class, 'password'], $auth);

        // Accountant (special screens on top of the generic list engine)
        $r->get('/accountant/chart-of-accounts/tree', [AccountController::class, 'tree'], $auth);
        $r->get('/accountant/chart-of-accounts/nav', [AccountController::class, 'nav'], $auth);
        $r->get('/accountant/accounts/search', [AccountController::class, 'search'], $auth);
        $r->get('/accountant/journals/new', [JournalController::class, 'form'], $auth);
        $r->get('/accountant/journals/{id:\d+}', [JournalController::class, 'show'], $auth);
        $r->get('/accountant/journals/{id:\d+}/edit', [JournalController::class, 'form'], $auth);
        $r->post('/accountant/journals/store', [JournalController::class, 'store'], $auth);

        // Sales & purchase documents
        $doc = '{module:sales|purchases}/{resource:quotes|invoices|credit-notes|orders}';
        $D = \App\Controllers\DocumentController::class;
        $r->get('/inventory/items/search', [$D, 'itemSearch'], $auth);
        $r->get("/$doc/new", [$D, 'form'], $auth);
        $r->post("/$doc/store", [$D, 'store'], $auth);
        $r->get("/$doc/{id:\d+}", [$D, 'show'], $auth);
        $r->get("/$doc/{id:\d+}/edit", [$D, 'form'], $auth);
        $r->get("/$doc/{id:\d+}/print", [$D, 'print'], $auth);
        $r->post("/$doc/{id:\d+}/payment", [$D, 'payment'], $auth);
        $r->get('/purchases/orders/{id:\d+}/receive', [$D, 'receiveForm'], $auth);
        $r->post('/purchases/orders/{id:\d+}/receive', [$D, 'receive'], $auth);

        // Reports
        $r->get('/reports/{report:trial-balance|ledger|profit-loss|balance-sheet|day-book}', [ReportController::class, 'show'], $auth);
        $r->get('/reports/{report:trial-balance|ledger|profit-loss|balance-sheet|day-book}/data', [ReportController::class, 'data'], $auth);

        // Settings
        $r->get('/settings/company', [CompanySettingsController::class, 'edit'], ['auth', 'admin']);
        $r->post('/settings/company', [CompanySettingsController::class, 'update'], ['auth', 'admin']);

        // Generic resource engine: list + filters + ajax pagination + drawer forms
        $res = '{module:[a-z]+}/{resource:[a-z][a-z\-]*}';
        $r->get("/$res", [ResourceController::class, 'index'], $auth);
        $r->get("/$res/data", [ResourceController::class, 'data'], $auth);
        $r->get("/$res/export", [ResourceController::class, 'export'], $auth);
        $r->get("/$res/form", [ResourceController::class, 'form'], $auth);
        $r->get("/$res/form/{id:\d+}", [ResourceController::class, 'form'], $auth);
        $r->post("/$res/save", [ResourceController::class, 'save'], $auth);
        $r->post("/$res/delete/{id:\d+}", [ResourceController::class, 'delete'], $auth);
        $r->post("/$res/action/{key:[a-z\-]+}/{id:\d+}", [ResourceController::class, 'action'], $auth);
    }

    private static function middleware(string $name): void
    {
        $auth = Auth::instance();
        switch ($name) {
            case 'auth':
                if (!$auth->check()) {
                    if (is_ajax()) {
                        json_out(['ok' => false, 'message' => 'Your session has expired. Please sign in again.', 'redirect' => url('/login')], 401);
                    }
                    Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/');
                    redirect('/login');
                }
                if (!$auth->company() && !$auth->isSuper()) {
                    $auth->logout('No active company');
                    redirect('/login');
                }
                // Force a password change when the admin requested it (temporary passwords)
                $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                if (!empty($auth->user()['must_change_password']) && !preg_match('#/(profile|logout)(/|$)#', $path)) {
                    if (is_ajax()) {
                        json_out(['ok' => false, 'message' => 'Please change your password first.'], 403);
                    }
                    Session::flash('info', 'Please set a new password before continuing.');
                    redirect('/profile?tab=security');
                }
                break;
            case 'guest':
                if ($auth->check()) {
                    redirect('/dashboard');
                }
                break;
            case 'admin':
                if (!$auth->isAdmin()) {
                    throw new HttpException(403);
                }
                break;
            case 'super':
                if (!$auth->isSuper()) {
                    throw new HttpException(403);
                }
                break;
        }
    }

    private static function securityHeaders(): void
    {
        header_remove('X-Powered-By');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        } elseif (config('app.force_https') && PHP_SAPI !== 'cli') {
            header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
    }

    public static function renderError(int $status, string $message): void
    {
        http_response_code($status);
        if (is_ajax() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json')) {
            json_out(['ok' => false, 'message' => $message], $status);
        }
        try {
            $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (preg_match('#/store/([a-z0-9\-]+)#', $path, $m)) { // public shop: customer-friendly page
                echo View::render('errors/store', ['status' => $status, 'message' => $message, 'title' => "Error $status",
                    'home' => \App\Services\Shop::store($m[1]) ? url('/store/' . $m[1]) : null], null);
                return;
            }
            echo View::render('errors/error', ['status' => $status, 'message' => $message, 'title' => "Error $status"], Auth::instance()->check() ? 'app' : 'auth');
        } catch (\Throwable $e) {
            error_log((string) $e);
            echo '<h1>' . $status . '</h1><p>' . e($message) . '</p>';
        }
    }
}
