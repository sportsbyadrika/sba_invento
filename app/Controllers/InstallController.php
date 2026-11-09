<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Services\Installer;

/**
 * Web installer for shared hosting without a terminal: https://your-domain/install
 *
 * Safety:
 *  - only works while config 'app.install_key' is set, and the same key must be typed in the form
 *  - disabled for good once storage/installed.lock exists or any user exists
 */
final class InstallController
{
    private const LOCK = BASE_PATH . '/storage/installed.lock';

    private function guard(): void
    {
        if (is_file(self::LOCK) || Installer::hasUsers()) {
            throw new HttpException(404, 'The application is already installed. Remove app.install_key from config/config.php.');
        }
        if ((string) config('app.install_key', '') === '') {
            throw new HttpException(403, "Installer disabled. Set 'install_key' in the 'app' section of config/config.php, then reload this page.");
        }
    }

    private function dbError(): ?string
    {
        try {
            Database::pdo()->query('SELECT 1');
            return null;
        } catch (\Throwable $e) {
            // never echo the password; PDO messages contain host / user / reason only
            return 'Cannot connect to the database: ' . preg_replace('/\(using password: \w+\)/', '', $e->getMessage())
                . '. Check the db section of config/config.php.';
        }
    }

    public function show(): string
    {
        $this->guard();
        return View::render('install/index', [
            'title' => 'Install',
            'dbError' => $this->dbError(),
            'checks' => $this->checks(),
        ], 'auth');
    }

    public function run(): string
    {
        $this->guard();
        $in = static fn (string $k) => trim((string) ($_POST[$k] ?? ''));
        $error = null;
        if (!hash_equals((string) config('app.install_key'), $in('install_key'))) {
            $error = 'The install key does not match app.install_key in config/config.php.';
        } elseif ($in('password') !== (string) ($_POST['password_confirmation'] ?? '')) {
            $error = 'The passwords do not match.';
        } elseif ($db = $this->dbError()) {
            $error = $db;
        }
        if (!$error) {
            try {
                @set_time_limit(300);
                Installer::schema();
                $id = Installer::superAdmin($in('username'), $in('email'), (string) $_POST['password'], $in('name') ?: 'Platform Super Admin');
                $log = '';
                if (!empty($_POST['demo'])) {
                    ob_start();
                    Installer::demo($id);
                    $log = (string) ob_get_clean();
                }
                file_put_contents(self::LOCK, gmdate('c') . "\n");
                Session::flash('success', 'Installation complete. Sign in with your super admin account.');
                return View::render('install/done', ['title' => 'Installed', 'username' => strtolower($in('username')), 'demo' => !empty($_POST['demo']), 'log' => $log], 'auth');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (\Throwable $e) {
                error_log((string) $e);
                $error = 'Installation failed: ' . $e->getMessage();
            }
        }
        return View::render('install/index', [
            'title' => 'Install', 'error' => $error, 'dbError' => null, 'checks' => $this->checks(),
            'old' => ['username' => $in('username'), 'name' => $in('name'), 'email' => $in('email')],
        ], 'auth');
    }

    /** @return array<int, array{0:string,1:bool,2:string}> */
    private function checks(): array
    {
        return [
            ['PHP 8.2 or newer', PHP_VERSION_ID >= 80200, 'Current: ' . PHP_VERSION . ' — change it in cPanel → MultiPHP Manager'],
            ['pdo_mysql extension', extension_loaded('pdo_mysql'), 'Enable in cPanel → Select PHP Version → Extensions'],
            ['mbstring extension', extension_loaded('mbstring'), 'Enable in cPanel → Select PHP Version → Extensions'],
            ['intl / sodium (recommended)', extension_loaded('sodium'), 'Optional'],
            ['storage/ is writable', is_writable(BASE_PATH . '/storage'), 'Set folder permission 755'],
            ['Argon2id password hashing', defined('PASSWORD_ARGON2ID'), 'Falls back to bcrypt if missing (still secure)'],
        ];
    }
}
