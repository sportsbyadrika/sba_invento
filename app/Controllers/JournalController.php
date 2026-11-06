<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\JournalService;
use App\Services\NumberSeries;

final class JournalController
{
    private function load(int $id): array
    {
        $j = Database::one(
            'SELECT j.*, u.name AS created_by_name, p.name AS posted_by_name FROM journals j
          LEFT JOIN users u ON u.id = j.created_by LEFT JOIN users p ON p.id = j.posted_by
              WHERE j.id = ? AND j.company_id = ?',
            [$id, Auth::instance()->companyId()]
        );
        if (!$j) {
            throw new HttpException(404, 'Journal not found.');
        }
        $j['lines'] = Database::all(
            'SELECT l.*, a.code AS account_code, a.name AS account_name FROM journal_lines l JOIN accounts a ON a.id = l.account_id
              WHERE l.journal_id = ? ORDER BY l.line_no',
            [$id]
        );
        return $j;
    }

    public function form(?string $id = null): string
    {
        $auth = Auth::instance();
        $journal = null;
        if ($id !== null) {
            if (!$auth->can('accountant', 'edit')) {
                throw new HttpException(403);
            }
            $journal = $this->load((int) $id);
            if ($journal['status'] !== 'draft') {
                redirect('/accountant/journals/' . (int) $id);
            }
        } elseif (!$auth->can('accountant', 'create')) {
            throw new HttpException(403);
        }
        if (!$journal && isset($_GET['copy'])) {
            $src = $this->load((int) $_GET['copy']);
            $journal = ['id' => null, 'number' => null, 'status' => 'draft', 'journal_date' => date('Y-m-d')] + $src;
        }
        return View::render('accountant/journal-form', [
            'title' => $journal && $journal['id'] ? 'Edit Journal ' . $journal['number'] : 'New Journal',
            'activeModule' => 'accountant',
            'journal' => $journal,
            'nextNumber' => NumberSeries::peek((int) $auth->companyId(), 'journal'),
            'currencies' => Database::all('SELECT code, name, decimal_places, exchange_rate, is_base FROM currencies WHERE company_id = ? AND is_active = 1 ORDER BY is_base DESC, code', [$auth->companyId()]),
        ]);
    }

    public function show(string $id): string
    {
        if (!Auth::instance()->can('accountant', 'view')) {
            throw new HttpException(403);
        }
        $j = $this->load((int) $id);
        return View::render('accountant/journal-show', ['title' => 'Journal ' . $j['number'], 'activeModule' => 'accountant', 'j' => $j]);
    }

    public function store(): never
    {
        $auth = Auth::instance();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$auth->can('accountant', $id ? 'edit' : 'create')) {
            throw new HttpException(403);
        }
        $lines = is_array($_POST['lines'] ?? null) ? array_values($_POST['lines']) : [];
        $post = ($_POST['submit_action'] ?? 'draft') === 'post';
        try {
            $newId = JournalService::save($_POST, $lines, $post);
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        }
        $number = Database::value('SELECT number FROM journals WHERE id = ?', [$newId]);
        json_out([
            'ok' => true,
            'id' => $newId,
            'message' => "Journal $number " . ($post ? 'posted.' : 'saved as draft.'),
            'redirect' => ($_POST['after'] ?? '') === 'new' ? url('/accountant/journals/new') : url('/accountant/journals/' . $newId),
        ]);
    }
}
