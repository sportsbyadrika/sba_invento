<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\View;
use App\Services\Ledger;

final class AccountController
{
    /** Hierarchical COA with closing balances (HTML fragment for the Tree tab). */
    public function tree(): string
    {
        $auth = Auth::instance();
        if (!$auth->can('accountant', 'view')) {
            throw new HttpException(403);
        }
        $map = Ledger::balances((int) $auth->companyId(), '1900-01-01', date('Y-m-d'));
        return View::partial('accountant/coa-tree', [
            'map' => $map,
            'order' => Ledger::ordered($map),
            'canEdit' => $auth->can('accountant', 'edit'),
        ]);
    }

    /** Compact group hierarchy shown beside the list (HTML fragment). */
    public function nav(): string
    {
        $auth = Auth::instance();
        if (!$auth->can('accountant', 'view')) {
            throw new HttpException(403);
        }
        $rows = Database::all(
            'SELECT id, parent_id, code, name, account_type, is_group, is_active FROM accounts WHERE company_id = ? ORDER BY code',
            [$auth->companyId()]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r + ['kids' => [], 'groups' => [], 'ledgers' => 0];
        }
        foreach ($map as $id => $r) {
            if ($r['parent_id'] && isset($map[(int) $r['parent_id']])) {
                $map[(int) $r['parent_id']]['kids'][] = $id;
                if ($r['is_group']) {
                    $map[(int) $r['parent_id']]['groups'][] = $id;
                }
            }
        }
        $count = static function (int $id) use (&$count, &$map): int {
            $n = 0;
            foreach ($map[$id]['kids'] as $k) {
                $n += $map[$k]['is_group'] ? $count($k) : 1;
            }
            return $map[$id]['ledgers'] = $n;
        };
        $order = [];
        $walk = static function (int $id, int $depth) use (&$walk, &$map, &$order): void {
            $map[$id]['depth'] = $depth;
            $order[] = $id;
            foreach ($map[$id]['groups'] as $g) {
                $walk($g, $depth + 1);
            }
        };
        foreach ($map as $id => $r) {
            if ($r['is_group'] && (!$r['parent_id'] || !isset($map[(int) $r['parent_id']]))) {
                $count($id);
                $walk($id, 0);
            }
        }
        return View::partial('accountant/coa-nav', ['map' => $map, 'order' => $order, 'total' => count($rows)]);
    }

    /** Account picker (ledgers only) - used by journal entry and report filters. */
    public function search(): never
    {
        $auth = Auth::instance();
        if (!$auth->check() || !$auth->companyId()) {
            throw new HttpException(403);
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $params = [$auth->companyId()];
        $sql = "SELECT a.id, a.code, a.name, a.account_type, p.name AS parent
                  FROM accounts a LEFT JOIN accounts p ON p.id = a.parent_id
                 WHERE a.company_id = ? AND a.is_group = 0 AND a.is_active = 1";
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 60), '%_\\') . '%';
            $sql .= ' AND (a.code LIKE ? OR a.name LIKE ? OR p.name LIKE ?)';
            array_push($params, addcslashes($q, '%_\\') . '%', $like, $like);
        }
        $sql .= ' ORDER BY (a.code LIKE ?) DESC, a.name LIMIT 20';
        $params[] = addcslashes($q, '%_\\') . '%';
        json_out(['ok' => true, 'items' => Database::all($sql, $params)]);
    }
}
