<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require BASE_PATH . '/app/helpers.php';

$configFile = BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php - copy config/config.example.php and update the database settings.');
}
App\Core\Config::load(require $configFile);

date_default_timezone_set('UTC'); // storage timezone is always UTC
mb_internal_encoding('UTF-8');

$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
// Show errors on screen only in debug mode, and never deprecation notices: printed output
// before headers breaks redirects and the session cookie. Everything still goes to the log.
ini_set('display_errors', $debug ? '1' : '0');
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    if ($no & (E_DEPRECATED | E_USER_DEPRECATED)) {
        error_log("PHP Deprecated: $msg in $file:$line");
        return true; // handled - not displayed
    }
    return false; // default PHP handling for everything else
});
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
