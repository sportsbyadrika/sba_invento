<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\DocumentService as Docs;
use App\Services\Stock;
use App\Services\TaxRules;

/** Quotes, invoices, credit notes and purchase orders: entry form, view, print, receive goods, payments. */
final class DocumentController
{
    private const PREFIX = ['sales/quotes' => 'quote', 'sales/invoices' => 'invoice', 'sales/credit-notes' => 'credit_note', 'purchases/orders' => 'po'];

    private function type(string $module, string $resource, string $action = 'view'): string
    {
        $type = self::PREFIX["$module/$resource"] ?? throw new HttpException(404);
        if (!Auth::instance()->can(Docs::TYPES[$type]['module'], $action)) {
            throw new HttpException(403);
        }
        return $type;
    }

    private function load(int $id, string $type): array
    {
        try {
            return Docs::find($id, $type);
        } catch (ValidationException) {
            throw new HttpException(404, 'Document not found.');
        }
    }

    public function form(string $module, string $resource, ?string $id = null): string
    {
        $type = $this->type($module, $resource, $id ? 'edit' : 'create');
        if ($type === 'credit_note') {
            throw new HttpException(404, 'Credit notes are created from returns.');
        }
        $cid = (int) Auth::instance()->companyId();
        $doc = null;
        if ($id) {
            $doc = $this->load((int) $id, $type);
            if ($doc['status'] !== 'draft') {
                redirect('/' . Docs::TYPES[$type]['path'] . '/' . (int) $id);
            }
        } elseif (isset($_GET['copy'])) {
            $src = $this->load((int) $_GET['copy'], $type);
            $doc = ['id' => null, 'number' => null, 'status' => 'draft', 'doc_date' => date('Y-m-d'), 'due_date' => null] + $src;
        }
        $cfg = Docs::TYPES[$type];
        return View::render('documents/form', [
            'title' => ($doc && $doc['id'] ? 'Edit ' : 'New ') . $cfg['label'],
            'activeModule' => $cfg['module'],
            'type' => $type, 'cfg' => $cfg, 'doc' => $doc,
            'nextNumber' => \App\Services\NumberSeries::peek($cid, $cfg['series']),
            'contacts' => Database::all('SELECT id, code, name, currency_code, place_of_supply, payment_terms, billing_address, shipping_address FROM contacts WHERE company_id = ? AND contact_type = ? AND is_active = 1 ORDER BY name', [$cid, $cfg['contact']]),
            'currencies' => Database::all('SELECT code, decimal_places, exchange_rate, is_base FROM currencies WHERE company_id = ? AND is_active = 1 ORDER BY is_base DESC, code', [$cid]),
            'taxes' => Database::all('SELECT id, name, rate, is_default FROM tax_rates WHERE company_id = ? AND is_active = 1 ORDER BY rate', [$cid]),
            'places' => TaxRules::places(Auth::instance()->company()['tax_regime']),
        ]);
    }

    public function store(string $module, string $resource): never
    {
        $id = (int) ($_POST['id'] ?? 0);
        $type = $this->type($module, $resource, $id ? 'edit' : 'create');
        $lines = is_array($_POST['lines'] ?? null) ? array_values($_POST['lines']) : [];
        $action = (string) ($_POST['submit_action'] ?? 'draft');
        try {
            $newId = Database::transaction(static function () use ($type, $lines, $action) {
                $newId = Docs::save($type, $_POST, $lines);
                if ($action === 'post') {
                    match ($type) {
                        'invoice' => Docs::postInvoice($newId),
                        'po' => Database::update('documents', ['status' => 'open'], 'id = ?', [$newId]),
                        'quote' => Database::update('documents', ['status' => 'sent'], 'id = ?', [$newId]),
                        default => null,
                    };
                }
                return $newId;
            });
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        }
        $cfg = Docs::TYPES[$type];
        $number = Database::value('SELECT number FROM documents WHERE id = ?', [$newId]);
        $verb = $action !== 'post' ? 'saved as draft' : ['invoice' => 'posted', 'po' => 'approved', 'quote' => 'marked as sent'][$type] ?? 'saved';
        json_out([
            'ok' => true, 'id' => $newId, 'message' => "{$cfg['label']} $number $verb.",
            'redirect' => ($_POST['after'] ?? '') === 'new' ? url('/' . $cfg['path'] . '/new') : url('/' . $cfg['path'] . '/' . $newId),
        ]);
    }

    public function show(string $module, string $resource, string $id): string
    {
        $type = $this->type($module, $resource);
        $doc = $this->load((int) $id, $type);
        $cid = (int) Auth::instance()->companyId();
        $related = Database::all(
            "SELECT id, doc_type, number, status, total FROM documents WHERE company_id = ? AND ((source_type = ? AND source_id = ?) OR id = ?) AND id <> ?",
            [$cid, $type, $doc['id'], $doc['source_type'] === 'quote' ? (int) $doc['source_id'] : 0, $doc['id']]
        );
        $payments = Database::all(
            "SELECT j.id, j.number, j.journal_date, j.total_base, j.narration FROM journals j WHERE j.company_id = ? AND j.source_id = ? AND j.source_type IN ('receipt','refund') AND j.status = 'posted' ORDER BY j.id",
            [$cid, $doc['id']]
        );
        if ($type === 'po') {
            $payments = []; // receipts of goods are listed from stock moves instead
        }
        return View::render('documents/show', [
            'title' => Docs::TYPES[$type]['label'] . ' ' . $doc['number'], 'activeModule' => Docs::TYPES[$type]['module'],
            'type' => $type, 'cfg' => Docs::TYPES[$type], 'doc' => $doc, 'related' => $related, 'payments' => $payments,
            'taxSummary' => Docs::taxSummary($doc),
            'cashAccounts' => Database::all("SELECT id, code, name FROM accounts WHERE company_id = ? AND is_group = 0 AND is_active = 1 AND sub_type IN ('cash','bank') ORDER BY code", [$cid]),
            'receipts' => $type === 'po' ? Database::all("SELECT m.move_date, m.reference, i.name, m.qty, m.unit_cost FROM stock_moves m JOIN items i ON i.id = m.item_id WHERE m.company_id = ? AND m.source_type = 'receipt' AND m.source_id = ? ORDER BY m.id", [$cid, $doc['id']]) : [],
        ]);
    }

    public function print(string $module, string $resource, string $id): string
    {
        $type = $this->type($module, $resource);
        $doc = $this->load((int) $id, $type);
        return View::render('documents/print', ['title' => Docs::TYPES[$type]['label'] . ' ' . $doc['number'], 'doc' => $doc, 'cfg' => Docs::TYPES[$type], 'taxSummary' => Docs::taxSummary($doc), 'company' => Auth::instance()->company()], null);
    }

    public function receiveForm(string $id): string
    {
        $this->type('purchases', 'orders', 'edit');
        $doc = $this->load((int) $id, 'po');
        if (!in_array($doc['status'], ['open', 'partial'], true)) {
            redirect('/purchases/orders/' . (int) $id);
        }
        $ids = array_filter(array_map(static fn ($l) => (int) $l['item_id'], $doc['lines']));
        $items = $ids ? Database::all('SELECT id, sales_price, purchase_price FROM items WHERE id IN (' . implode(',', $ids) . ')') : [];
        return View::render('documents/receive', [
            'title' => 'Receive goods · ' . $doc['number'], 'activeModule' => 'purchases', 'doc' => $doc,
            'items' => array_column($items, null, 'id'),
            'onHand' => Stock::onHand((int) Auth::instance()->companyId(), $ids),
        ]);
    }

    public function receive(string $id): never
    {
        $this->type('purchases', 'orders', 'edit');
        try {
            Docs::receivePo((int) $id, (array) ($_POST['receive'] ?? []), max(0, (float) str_replace(',', '', (string) ($_POST['freight'] ?? 0))),
                (string) ($_POST['receipt_date'] ?? ''), trim((string) ($_POST['bill_ref'] ?? '')) ?: null, (array) ($_POST['price'] ?? []));
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        }
        json_out(['ok' => true, 'message' => 'Goods received and added to stock.', 'redirect' => url('/purchases/orders/' . (int) $id)]);
    }

    public function payment(string $module, string $resource, string $id): never
    {
        $type = $this->type($module, $resource, 'edit');
        try {
            Docs::recordPayment((int) $id, (float) str_replace(',', '', (string) ($_POST['amount'] ?? 0)), (int) ($_POST['account_id'] ?? 0), (string) ($_POST['date'] ?? date('Y-m-d')), trim((string) ($_POST['reference'] ?? '')) ?: null);
        } catch (ValidationException $v) {
            json_out(['ok' => false, 'message' => $v->getMessage(), 'errors' => $v->errors], 422);
        }
        json_out(['ok' => true, 'message' => $type === 'credit_note' ? 'Refund recorded.' : 'Payment recorded.', 'redirect' => url('/' . Docs::TYPES[$type]['path'] . '/' . (int) $id)]);
    }

    /** Item picker for document lines */
    public function itemSearch(): never
    {
        $auth = Auth::instance();
        if (!$auth->companyId()) {
            throw new HttpException(403);
        }
        $cid = (int) $auth->companyId();
        $q = trim((string) ($_GET['q'] ?? ''));
        $p = [$cid];
        $sql = 'SELECT i.id, i.sku, i.name, i.sales_price, i.purchase_price, i.tax_rate_id, i.track_inventory, u.code AS unit
                  FROM items i LEFT JOIN units u ON u.id = i.unit_id WHERE i.company_id = ? AND i.is_active = 1';
        if ($q !== '') {
            $like = '%' . addcslashes(mb_substr($q, 0, 60), '%_\\') . '%';
            $sql .= ' AND (i.sku LIKE ? OR i.name LIKE ? OR i.barcode = ?)';
            array_push($p, $like, $like, $q);
        }
        $items = Database::all($sql . ' ORDER BY i.name LIMIT 20', $p);
        $stock = Stock::onHand($cid, array_column($items, 'id'));
        foreach ($items as &$i) {
            $i['on_hand'] = $i['track_inventory'] ? ($stock[(int) $i['id']] ?? 0) : null;
        }
        json_out(['ok' => true, 'items' => $items]);
    }
}
