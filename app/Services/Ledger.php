<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Account balances from the single general ledger (posted journals only).
 * Signed amounts: positive = debit, negative = credit (base currency).
 */
final class Ledger
{
    /**
     * @return array<int,array> account rows keyed by id with ob (opening at $from), dr, cr, cb (closing) and
     *         rolled-up values for group accounts; plus 'children' ids and 'depth'.
     */
    public static function balances(int $companyId, string $from, string $to, ?array $types = null): array
    {
        $typeSql = '';
        $params = [$from, $from, $to, $from, $to, $to, $companyId];
        if ($types) {
            $typeSql = ' AND a.account_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            array_push($params, ...$types);
        }
        $rows = Database::all(
            "SELECT a.id, a.parent_id, a.code, a.name, a.account_type, a.sub_type, a.is_group, a.is_active,
                    (CASE WHEN a.opening_type = 'Dr' THEN a.opening_balance ELSE -a.opening_balance END) AS ob_master,
                    COALESCE(SUM(CASE WHEN j.journal_date < ? THEN l.debit_base - l.credit_base END), 0) AS pre,
                    COALESCE(SUM(CASE WHEN j.journal_date >= ? AND j.journal_date <= ? THEN l.debit_base END), 0) AS dr,
                    COALESCE(SUM(CASE WHEN j.journal_date >= ? AND j.journal_date <= ? THEN l.credit_base END), 0) AS cr
               FROM accounts a
          LEFT JOIN journal_lines l ON l.account_id = a.id
          LEFT JOIN journals j ON j.id = l.journal_id AND j.status = 'posted' AND j.journal_date <= ?
              WHERE a.company_id = ? $typeSql
           GROUP BY a.id
           ORDER BY a.code",
            $params
        );
        $map = [];
        foreach ($rows as $r) {
            $r['ob'] = (float) $r['ob_master'] + (float) $r['pre'];
            $r['dr'] = (float) $r['dr'];
            $r['cr'] = (float) $r['cr'];
            $r['cb'] = $r['ob'] + $r['dr'] - $r['cr'];
            $r['children'] = [];
            $map[(int) $r['id']] = $r;
        }
        foreach ($map as $id => $r) {
            if ($r['parent_id'] && isset($map[(int) $r['parent_id']])) {
                $map[(int) $r['parent_id']]['children'][] = $id;
            }
        }
        // roll up group totals (post-order)
        $roll = static function (int $id) use (&$roll, &$map): array {
            $t = [$map[$id]['ob'], $map[$id]['dr'], $map[$id]['cr']];
            foreach ($map[$id]['children'] as $cid) {
                $c = $roll($cid);
                $t[0] += $c[0];
                $t[1] += $c[1];
                $t[2] += $c[2];
            }
            [$map[$id]['ob'], $map[$id]['dr'], $map[$id]['cr']] = $t;
            $map[$id]['cb'] = $t[0] + $t[1] - $t[2];
            return $t;
        };
        $depth = static function (int $id, int $d) use (&$depth, &$map): void {
            $map[$id]['depth'] = $d;
            foreach ($map[$id]['children'] as $cid) {
                $depth($cid, $d + 1);
            }
        };
        foreach ($map as $id => $r) {
            if (!$r['parent_id'] || !isset($map[(int) $r['parent_id']])) {
                $roll($id);
                $depth($id, 0);
            }
        }
        return $map;
    }

    /** Depth-first ordered list of account ids (roots ordered by code) */
    public static function ordered(array $map): array
    {
        $out = [];
        $walk = static function (int $id) use (&$walk, &$out, $map): void {
            $out[] = $id;
            $kids = $map[$id]['children'];
            usort($kids, static fn ($a, $b) => strcmp($map[$a]['code'], $map[$b]['code']));
            foreach ($kids as $k) {
                $walk($k);
            }
        };
        foreach ($map as $id => $r) {
            if (!$r['parent_id'] || !isset($map[(int) $r['parent_id']])) {
                $walk($id);
            }
        }
        return $out;
    }
}
