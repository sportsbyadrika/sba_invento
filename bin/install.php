<?php
declare(strict_types=1);

/**
 * CLI installer (when you have SSH / Terminal). Without a terminal use the web installer: https://your-domain/install
 *
 *   php bin/install.php --super-user=superadmin --super-email=you@example.com --super-pass='Str0ngPass!'
 *   php bin/install.php --demo           # also creates two demo companies with sample data
 *   php bin/install.php --fresh --demo   # DROPS all tables first (development only)
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Services\Installer;

$opt = getopt('', ['super-user::', 'super-email::', 'super-pass::', 'demo', 'fresh']);

if (isset($opt['fresh'])) {
    echo "Dropping existing tables...\n";
    Installer::dropAll();
}
echo "Creating schema...\n";
Installer::schema();

$user = (string) ($opt['super-user'] ?? 'superadmin');
$pass = (string) ($opt['super-pass'] ?? 'Super@12345');
try {
    $superId = Installer::superAdmin($user, (string) ($opt['super-email'] ?? 'superadmin@example.com'), $pass);
} catch (InvalidArgumentException $e) {
    exit('Super admin: ' . $e->getMessage() . "\n");
}
echo "Super admin ready: $user\n";

if (isset($opt['demo'])) {
    echo "Seeding demo companies...\n";
    Installer::demo($superId);
}
@touch(BASE_PATH . '/storage/installed.lock');
echo "Done.\n";
