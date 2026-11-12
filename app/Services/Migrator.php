<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Applies database/migrations/*.sql once each, in name order, and records them in `migrations`.
 * Runs automatically on the first request after a deploy (no terminal needed), from the
 * installer, and from System Health.
 */
final class Migrator
{
    private const DIR = BASE_PATH . '/database/migrations';
    private const STAMP = BASE_PATH . '/storage/cache/migrations.stamp';

    /** @return string[] migration names on disk */
    public static function files(): array
    {
        $files = glob(self::DIR . '/*.sql') ?: [];
        sort($files);
        return array_map(static fn ($f) => basename($f, '.sql'), $files);
    }

    /** Cheap check (no DB query) used on every request */
    public static function needsCheck(): bool
    {
        $sig = implode(',', self::files());
        return !is_file(self::STAMP) || trim((string) @file_get_contents(self::STAMP)) !== $sig;
    }

    public static function pending(): array
    {
        self::ensureTable();
        $done = array_column(Database::all('SELECT name FROM migrations'), 'name');
        return array_values(array_diff(self::files(), $done));
    }

    /** @return string[] names applied now */
    public static function run(): array
    {
        $lock = @fopen(BASE_PATH . '/storage/cache/migrations.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX); // two simultaneous first requests must not run the same migration twice
        }
        try {
            $applied = [];
            foreach (self::pending() as $name) {
                $sql = (string) file_get_contents(self::DIR . "/$name.sql");
                $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    Database::pdo()->exec($stmt);
                }
                Database::insert('migrations', ['name' => $name, 'applied_at' => now_utc()]);
                $applied[] = $name;
            }
            @file_put_contents(self::STAMP, implode(',', self::files()));
            return $applied;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** Marks every migration as applied (fresh install: schema.sql already has the latest tables) */
    public static function ensureTable(): void
    {
        Database::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
