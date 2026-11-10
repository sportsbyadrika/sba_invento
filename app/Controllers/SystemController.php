<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;

/**
 * Super admin: System Health. Measures each layer on the real server so slowness can be
 * attributed to PHP, the database, the disk/session store or the network.
 */
final class SystemController
{
    public function index(): string
    {
        $ms = static fn (float $t0) => round((microtime(true) - $t0) * 1000, 1);
        $checks = [];
        $add = static function (string $group, string $name, string $value, string $status = 'ok', string $hint = '') use (&$checks) {
            $checks[$group][] = compact('name', 'value', 'status', 'hint');
        };

        // ---- Deployment
        $dep = @parse_ini_file(BASE_PATH . '/storage/deployed.txt') ?: [];
        $add('Deployment', 'Deployed version', $dep ? (($dep['commit'] ?? '?') . ' · ' . ($dep['branch'] ?? '?') . ' · ' . ($dep['time'] ?? '?')) : 'unknown (deployed without bin/deploy.sh)', $dep ? 'ok' : 'warn',
            'Deploy with cPanel Git Version Control so bin/deploy.sh records the version.');
        $ht = (string) @file_get_contents(BASE_PATH . '/public/.htaccess');
        $add('Deployment', 'URL rewriting', isset($_SERVER['SBA_REWRITE']) || isset($_SERVER['REDIRECT_SBA_REWRITE']) ? 'active (clean URLs)' : 'not active - using /index.php/... URLs',
            isset($_SERVER['SBA_REWRITE']) || isset($_SERVER['REDIRECT_SBA_REWRITE']) ? 'ok' : 'warn', 'public/.htaccess is missing or ignored by the web server.');
        $add('Deployment', 'cPanel PHP handler in public/.htaccess', str_contains($ht, 'cPanel-generated handler') ? 'present' : 'none (server default PHP is used)',
            str_contains($ht, 'cPanel-generated handler') ? 'ok' : 'warn', 'Re-select the PHP version in cPanel → MultiPHP Manager to write it again.');

        // ---- PHP
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $add('PHP', 'Version / SAPI', PHP_VERSION . ' / ' . PHP_SAPI, PHP_VERSION_ID >= 80200 ? 'ok' : 'bad');
        $add('PHP', 'Web server', (string) ($_SERVER['SERVER_SOFTWARE'] ?? '?'));
        if ($opcache && !empty($opcache['opcache_enabled'])) {
            $st = $opcache['opcache_statistics'] ?? [];
            $add('PHP', 'OPcache', sprintf('enabled · hit rate %.1f%% · %d scripts', $st['opcache_hit_rate'] ?? 0, $st['num_cached_scripts'] ?? 0), ($st['opcache_hit_rate'] ?? 0) > 90 ? 'ok' : 'warn',
                'A low hit rate right after a restart is normal.');
        } else {
            $add('PHP', 'OPcache', 'disabled', 'bad', 'Every request recompiles all PHP files. Enable "opcache" in cPanel → Select PHP Version → Extensions (biggest single speed-up).');
        }
        $add('PHP', 'memory_limit / max_execution_time', ini_get('memory_limit') . ' / ' . ini_get('max_execution_time') . 's');
        $add('PHP', 'realpath_cache_size', (string) ini_get('realpath_cache_size'));

        // ---- Database
        $c = config('db');
        $add('Database', 'Host', $c['host'] . ':' . ($c['port'] ?? 3306), in_array($c['host'], ['localhost', '127.0.0.1'], true) ? 'ok' : 'warn',
            'A remote database host adds network latency to every query.');
        $add('Database', 'Connect time (this request)', round(Database::$connectMs, 1) . ' ms', Database::$connectMs < 20 ? 'ok' : (Database::$connectMs < 100 ? 'warn' : 'bad'),
            'On cPanel, "localhost" (socket) is usually faster than "127.0.0.1" (TCP). Above ~50 ms the server is overloaded or DNS lookups are slow.');
        $t0 = microtime(true);
        for ($i = 0; $i < 20; $i++) {
            Database::value('SELECT 1');
        }
        $rt = round($ms($t0) / 20, 2);
        $add('Database', 'Round trip (avg of 20 × SELECT 1)', $rt . ' ms', $rt < 1 ? 'ok' : ($rt < 5 ? 'warn' : 'bad'), 'Above ~5 ms per query the MySQL server is busy or far away. A list page runs about 6–10 queries.');
        $t0 = microtime(true);
        Database::all('SELECT a.id, COUNT(l.id) FROM accounts a LEFT JOIN journal_lines l ON l.account_id = a.id GROUP BY a.id');
        $q = $ms($t0);
        $add('Database', 'Ledger aggregate query', $q . ' ms', $q < 50 ? 'ok' : ($q < 300 ? 'warn' : 'bad'));
        $add('Database', 'Server version', (string) Database::value('SELECT VERSION()'));

        // ---- Disk & sessions
        $t0 = microtime(true);
        $f = BASE_PATH . '/storage/cache/.write-test';
        $ok = @file_put_contents($f, str_repeat('x', 4096)) !== false;
        @unlink($f);
        $w = $ms($t0);
        $add('Disk & sessions', 'storage/ write 4 KB', $ok ? $w . ' ms' : 'not writable', !$ok ? 'bad' : ($w < 20 ? 'ok' : 'warn'), 'Slow disk (network storage) also slows PHP sessions.');
        $add('Disk & sessions', 'Session handler / path', ini_get('session.save_handler') . ' · ' . (session_save_path() ?: sys_get_temp_dir()));

        // ---- Network (only used at login for IP location)
        if (config('geoip.enabled')) {
            $t0 = microtime(true);
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
            $r = @file_get_contents(str_replace('{ip}', '8.8.8.8', (string) config('geoip.endpoint')), false, $ctx);
            $g = $ms($t0);
            $add('Network', 'IP-location service (login only)', $r !== false ? $g . ' ms' : 'unreachable (' . $g . ' ms)', $r === false ? 'warn' : ($g < 800 ? 'ok' : 'warn'),
                'Used only when someone signs in. Failures are cached, so it cannot slow normal pages. You can set geoip.enabled = false.');
        }

        $total = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1);
        return View::render('admin/system', [
            'title' => 'System Health', 'activeModule' => 'admin', 'checks' => $checks, 'total' => $total,
        ]);
    }
}
