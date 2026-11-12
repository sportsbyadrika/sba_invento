<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Resource;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Audit;

/**
 * Generic list / filter / form controller for every declarative resource.
 *   GET  /{module}/{resource}            list page (shell only - data loads by ajax)
 *   GET  /{module}/{resource}/data       JSON page of rows + overview stats
 *   GET  /{module}/{resource}/export     CSV (Excel friendly) using the same filters
 *   GET  /{module}/{resource}/form[/id]  drawer form HTML fragment
 *   POST /{module}/{resource}/save       create / update
 *   POST /{module}/{resource}/delete/id
 *   POST /{module}/{resource}/action/key/id
 */
final class ResourceController
{
    private function resource(string $module, string $resource): Resource
    {
        $planned = PlannedController::find('/' . $module . '/' . $resource);
        if ($planned) {
            throw new PlannedException($planned);
        }
        $r = Resource::load($module, $resource);
        if (!$r) {
            throw new HttpException(404);
        }
        return $r;
    }

    public function index(string $module, string $resource): string
    {
        try {
            $r = $this->resource($module, $resource);
        } catch (PlannedException $p) {
            return (new PlannedController())->show($p->item);
        }
        $r->authorize('view');
        return View::render('partials/list', [
            'title' => $r->c['title'],
            'r' => $r,
            'activeModule' => $r->c['menu_module'] ?? $module,
        ]);
    }

    public function data(string $module, string $resource): never
    {
        $r = $this->resource($module, $resource);
        $r->authorize('view');
        $perPageOptions = (array) config('list.per_page_options', [10, 25, 50, 100, 250]);
        $perPage = (int) ($_GET['per_page'] ?? config('list.default_per_page', 25));
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = (int) config('list.default_per_page', 25);
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $r->fetch($_GET, $perPage, ($page - 1) * $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));
        if ($page > $pages && $result['total'] > 0) {
            $page = $pages;
            $result = $r->fetch($_GET, $perPage, ($page - 1) * $perPage);
        }
        $hasForm = (bool) array_filter($r->c['fields'], static fn ($f) => ($f['type'] ?? 'text') !== 'section');
        $canEdit = $hasForm && $r->allowed('edit');
        $canDelete = $r->allowed('delete');
        $rows = [];
        foreach ($result['rows'] as $row) {
            $cells = [];
            foreach ($r->c['columns'] as $key => $col) {
                $cells[$key] = $r->cell($key, $row);
            }
            $actions = [];
            foreach ($r->c['row_actions'] as $akey => $a) {
                if (!empty($a['permission']) && !$r->allowed($a['permission'])) {
                    continue;
                }
                if (isset($a['visible']) && !$a['visible']($row)) {
                    continue;
                }
                $actions[] = ['key' => $akey, 'label' => $a['label'], 'icon' => $a['icon'] ?? 'zap', 'confirm' => $a['confirm'] ?? null, 'tone' => $a['tone'] ?? 'slate'];
            }
            $deletable = $canDelete && (!isset($r->c['can_delete_row']) || ($r->c['can_delete_row'])($row));
            $rows[] = [
                'id' => (int) $row['__id'],
                'cells' => $cells,
                'url' => isset($r->c['row_url']) ? url((string) preg_replace_callback('/\{(\w+)\}/', static fn ($m) => rawurlencode((string) ($m[1] === 'id' ? $row['__id'] : ($row[$m[1]] ?? ''))), $r->c['row_url'])) : null,
                'edit' => $canEdit && (!isset($r->c['can_edit_row']) || ($r->c['can_edit_row'])($row)),
                'view' => $hasForm,
                'delete' => $deletable,
                'actions' => $actions,
            ];
        }
        json_out([
            'ok' => true,
            'rows' => $rows,
            'total' => $result['total'],
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'from' => $result['total'] ? ($page - 1) * $perPage + 1 : 0,
            'to' => min($result['total'], $page * $perPage),
            'stats' => ($_GET['stats'] ?? '1') === '1' ? $r->stats($_GET) : null,
        ]);
    }

    public function export(string $module, string $resource): never
    {
        $r = $this->resource($module, $resource);
        $r->authorize('view');
        if (!$r->c['export']) {
            throw new HttpException(403);
        }
        $result = $r->fetch($_GET, 50000);
        $filename = str_slug($r->c['title']) . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Arabic / Hindi / ₹ correctly
        $cols = array_filter($r->c['columns'], static fn ($c) => empty($c['no_export']));
        fputcsv($out, array_map(static fn ($c) => $c['label'], $cols), ',', '"', '\\');
        foreach ($result['rows'] as $row) {
            $line = [];
            foreach (array_keys($cols) as $key) {
                $v = $r->plain($key, $row);
                $line[] = preg_match('/^[=+\-@]/', $v) && !is_numeric($v) ? "'" . $v : $v; // CSV formula injection guard
            }
            fputcsv($out, $line, ',', '"', '\\');
        }
        fclose($out);
        Audit::log($r->key, null, 'export', null, ['rows' => count($result['rows'])]);
        exit;
    }

    public function form(string $module, string $resource, ?string $id = null): string
    {
        $r = $this->resource($module, $resource);
        $existing = null;
        if ($id !== null) {
            $r->authorize('view');
            $existing = $r->find((int) $id);
            if (!$existing) {
                throw new HttpException(404, 'Record not found.');
            }
            if (isset($r->c['hooks']['load'])) {
                $existing = $r->c['hooks']['load']($existing);
            }
        } else {
            if (!$r->allowed('create')) {
                throw new HttpException(403);
            }
        }
        $readonly = $existing ? !$r->allowed('edit') || (isset($r->c['can_edit_row']) && !($r->c['can_edit_row'])($existing)) : false;
        return View::partial('partials/form', ['r' => $r, 'row' => $existing, 'readonly' => $readonly]);
    }

    public function save(string $module, string $resource): never
    {
        $r = $this->resource($module, $resource);
        $id = (int) ($_POST['id'] ?? 0);
        $existing = null;
        if ($id > 0) {
            if (!$r->allowed('edit')) {
                throw new HttpException(403);
            }
            $existing = $r->find($id);
            if (!$existing) {
                throw new HttpException(404, 'Record not found.');
            }
            if (isset($r->c['can_edit_row']) && !($r->c['can_edit_row'])($existing)) {
                throw new HttpException(403, 'This record can no longer be edited.');
            }
        } elseif (!$r->allowed('create')) {
            throw new HttpException(403);
        }

        try {
            $input = $_POST;
            $data = $r->validate($input, $existing);
            $id = Database::transaction(function () use ($r, $data, $existing, $input) {
                if (isset($r->c['hooks']['before_save'])) {
                    $data = $r->c['hooks']['before_save']($data, $existing, $input);
                }
                $virtual = array_keys(array_filter($r->c['fields'], static fn ($f) => !empty($f['virtual'])));
                $row = array_diff_key($data, array_flip($virtual));
                foreach ($row as $k => $v) {
                    if (str_starts_with($k, '_')) {
                        unset($row[$k]);
                    }
                }
                if ($existing) {
                    $where = $r->c['pk'] . ' = ?';
                    $params = [$existing[$r->c['pk']]];
                    if ($tc = $r->tenantColumn()) {
                        $where .= " AND `$tc` = ?";
                        $params[] = Auth::instance()->companyId();
                    }
                    Database::update($r->c['table'], $row, $where, $params);
                    $newId = (int) $existing[$r->c['pk']];
                    Audit::log($r->key, $newId, 'update', $existing, $row);
                } else {
                    if ($tc = $r->tenantColumn()) {
                        $row[$tc] = Auth::instance()->companyId();
                    }
                    foreach ($r->c['scope'] as $col => $val) {
                        $row[$col] = $val;
                    }
                    if ($r->c['creator']) {
                        $row['created_by'] = Auth::instance()->id();
                    }
                    $newId = Database::insert($r->c['table'], $row);
                    Audit::log($r->key, $newId, 'create', null, $row);
                }
                if (isset($r->c['hooks']['after_save'])) {
                    $r->c['hooks']['after_save']($newId, $data, $existing, $input);
                }
                return $newId;
            });
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        } catch (\PDOException $e) {
            error_log((string) $e);
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'A record with the same code already exists.' : 'The record could not be saved.';
            json_out(['ok' => false, 'message' => $msg], 422);
        }
        json_out(['ok' => true, 'id' => $id, 'message' => $r->c['singular'] . ($existing ? ' updated.' : ' created.')]);
    }

    public function delete(string $module, string $resource, string $id): never
    {
        $r = $this->resource($module, $resource);
        if (!$r->allowed('delete')) {
            throw new HttpException(403);
        }
        $row = $r->find((int) $id);
        if (!$row) {
            throw new HttpException(404, 'Record not found.');
        }
        if (isset($r->c['hooks']['before_delete']) && ($err = $r->c['hooks']['before_delete']($row))) {
            json_out(['ok' => false, 'message' => $err], 422);
        }
        try {
            $where = $r->c['pk'] . ' = ?';
            $params = [(int) $id];
            if ($tc = $r->tenantColumn()) {
                $where .= " AND `$tc` = ?";
                $params[] = Auth::instance()->companyId();
            }
            Database::query('DELETE FROM `' . $r->c['table'] . "` WHERE $where", $params);
        } catch (\PDOException $e) {
            json_out(['ok' => false, 'message' => 'This record is in use and cannot be deleted. Mark it inactive instead.'], 422);
        }
        Audit::log($r->key, (int) $id, 'delete', $row, null);
        json_out(['ok' => true, 'message' => $r->c['singular'] . ' deleted.']);
    }

    public function action(string $module, string $resource, string $key, string $id): never
    {
        $r = $this->resource($module, $resource);
        $a = $r->c['row_actions'][$key] ?? null;
        if (!$a) {
            throw new HttpException(404);
        }
        $r->authorize($a['permission'] ?? 'edit');
        $row = $r->find((int) $id);
        if (!$row || (isset($a['visible']) && !$a['visible']($row))) {
            throw new HttpException(404, 'Record not found.');
        }
        try {
            $message = Database::transaction(static fn () => $a['handler']($row));
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage()], 422);
        }
        Audit::log($r->key, (int) $id, $key);
        if (is_array($message)) {
            json_out(['ok' => true] + $message);
        }
        json_out(['ok' => true, 'message' => $message ?: 'Done.']);
    }
}
