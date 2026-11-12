<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/** Finds the posting accounts of a company by behaviour (sub_type) or by seeded code. */
final class Accounts
{
    private static array $cache = [];

    public static function bySubType(int $cid, string $subType, string $fallbackCode = ''): int
    {
        $k = "$cid:$subType:$fallbackCode";
        if (!isset(self::$cache[$k])) {
            $id = Database::value('SELECT id FROM accounts WHERE company_id = ? AND sub_type = ? AND is_group = 0 AND is_active = 1 ORDER BY code LIMIT 1', [$cid, $subType]);
            if (!$id && $fallbackCode !== '') {
                $id = Database::value('SELECT id FROM accounts WHERE company_id = ? AND code = ? AND is_group = 0', [$cid, $fallbackCode]);
            }
            if (!$id) {
                throw new ValidationException([], "No active ledger with behaviour \"$subType\" found in the chart of accounts. Create one (or set the behaviour on an existing ledger) and try again.");
            }
            self::$cache[$k] = (int) $id;
        }
        return self::$cache[$k];
    }

    public static function byCode(int $cid, string $code): ?int
    {
        $id = Database::value('SELECT id FROM accounts WHERE company_id = ? AND code = ? AND is_group = 0', [$cid, $code]);
        return $id ? (int) $id : null;
    }
}
