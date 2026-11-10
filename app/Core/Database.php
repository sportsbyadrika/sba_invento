<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** Thin PDO wrapper - always prepared statements, UTC session time zone. */
final class Database
{
    private static ?PDO $pdo = null;
    /** Performance counters (sent as a Server-Timing header) */
    public static float $connectMs = 0.0;
    public static float $queryMs = 0.0;
    public static int $queries = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $t0 = microtime(true);
            $c = config('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'] ?? 3306, $c['database'], $c['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
            self::$connectMs = (microtime(true) - $t0) * 1000;
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::pdo();
        $t0 = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        self::$queryMs += (microtime(true) - $t0) * 1000;
        self::$queries++;
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', array_map(static fn ($c) => "`$c`", $cols)),
            implode(',', array_fill(0, count($cols), '?'))
        );
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        if (!$data) {
            return 0;
        }
        $set = implode(',', array_map(static fn ($c) => "`$c` = ?", array_keys($data)));
        return self::query("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$params])->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
