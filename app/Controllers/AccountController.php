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
