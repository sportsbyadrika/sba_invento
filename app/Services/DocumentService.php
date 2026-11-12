<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;

/**
 * Sales & purchase documents (quote, invoice, credit note, purchase order) and their postings.
 * All ledger postings go through JournalService in base currency, linked by source_type/source_id.
 */
final class DocumentService
{
    public const TYPES = [
        'quote' => ['label' => 'Quote', 'plural' => 'Quotes', 'contact' => 'customer', 'series' => 'quote', 'path' => 'sales/quotes', 'module' => 'sales', 'due' => 'Valid until'],
        'invoice' => ['label' => 'Invoice', 'plural' => 'Invoices', 'contact' => 'customer', 'series' => 'invoice', 'path' => 'sales/invoices', 'module' => 'sales', 'due' => 'Due date'],
        'credit_note' => ['label' => 'Credit Note', 'plural' => 'Credit Notes', 'contact' => 'customer', 'series' => 'credit_note', 'path' => 'sales/credit-notes', 'module' => 'sales', 'due' => 'Due date'],
        'po' => ['label' => 'Purchase Order', 'plural' => 'Purchase Orders', 'contact' => 'vendor', 'series' => 'po', 'path' => 'purchases/orders', 'module' => 'purchases', 'due' => 'Expected on'],
    ];

    public const STATUS = [
        'draft' => ['Draft', 'amber'], 'sent' => ['Sent', 'blue'], 'accepted' => ['Accepted', 'green'], 'declined' => ['Declined', 'rose'],
        'converted' => ['Invoiced', 'violet'], 'open' => ['Open', 'blue'], 'partial' => ['Partly received', 'cyan'], 'received' => ['Received', 'green'],
        'closed' => ['Closed', 'slate'], 'posted' => ['Unpaid', 'amber'], 'partly_paid' => ['Partly paid', 'cyan'], 'paid' => ['Paid', 'green'], 'void' => ['Void', 'slate'],
    ];

    private static function cid(): int
    {
        return (int) Auth::instance()->companyId();
    }

    public static function find(int $id, ?string $type = null): array
    {
        $sql = 'SELECT d.*, c.name AS contact_name, c.code AS contact_code, c.email AS contact_email, c.phone AS contact_phone, c.tax_number AS contact_tax_number,
                       c.billing_address AS contact_address, u.name AS created_by_name
                  FROM documents d JOIN contacts c ON c.id = d.contact_id LEFT JOIN users u ON u.id = d.created_by
                 WHERE d.id = ? AND d.company_id = ?';
        $p = [$id, self::cid()];
        if ($type) {
            $sql .= ' AND d.doc_type = ?';
            $p[] = $type;
        }
        $d = Database::one($sql, $p);
        if (!$d) {
            throw new ValidationException([], 'Document not found.');
        }
        $d['lines'] = Database::all(
            'SELECT l.*, i.sku, i.name AS item_name, i.track_inventory, i.item_type, u.code AS unit
               FROM document_lines l LEFT JOIN items i ON i.id = l.item_id LEFT JOIN units u ON u.id = i.unit_id
              WHERE l.document_id = ? ORDER BY l.line_no',
            [$id]
        );
        return $d;
    }

    /** Line & document totals (rounded to the currency decimals) */
    public static function compute(array $lines, float $freight, int $dec): array
    {
        $sub = $disc = $tax = 0.0;
        foreach ($lines as &$l) {
            $gross = round($l['qty'] * $l['unit_price'], $dec);
            $l['line_total'] = round($gross * (1 - $l['discount_pct'] / 100), $dec);
            $l['tax_amount'] = round($l['line_total'] * $l['tax_pct'] / 100, $dec);
            $sub += $l['line_total'];
            $disc += $gross - $l['line_total'];
            $tax += $l['tax_amount'];
        }
        unset($l);
        $freight = round($freight, $dec);
        return [$lines, ['subtotal' => round($sub, $dec), 'discount_total' => round($disc, $dec), 'tax_total' => round($tax, $dec), 'freight' => $freight, 'total' => round($sub + $tax + $freight, $dec)]];
    }

    /** Create or update a draft. @return int document id */
    public static function save(string $type, array $h, array $rawLines, string $sourceType = 'manual', ?int $sourceId = null): int
    {
        $cfg = self::TYPES[$type] ?? throw new ValidationException([], 'Unknown document type.');
        $cid = self::cid();
        $company = Auth::instance()->company();
        $errors = [];
        $date = (string) ($h['doc_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['doc_date'] = 'Enter a valid date.';
        }
        $due = (string) ($h['due_date'] ?? '');
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $errors['due_date'] = 'Enter a valid date.';
        }
        $contact = Database::one('SELECT * FROM contacts WHERE id = ? AND company_id = ? AND contact_type = ?', [(int) ($h['contact_id'] ?? 0), $cid, $cfg['contact']]);
        if (!$contact) {
            $errors['contact_id'] = 'Select a ' . $cfg['contact'] . '.';
        }
        $currency = strtoupper((string) ($h['currency_code'] ?? $company['base_currency']));
        $cur = Database::one('SELECT * FROM currencies WHERE company_id = ? AND code = ? AND is_active = 1', [$cid, $currency]);
        if (!$cur) {
            $errors['currency_code'] = 'Select an active currency.';
        }
        $rate = $currency === $company['base_currency'] ? 1.0 : (float) str_replace(',', '', (string) ($h['exchange_rate'] ?? 0));
        if ($rate <= 0) {
            $errors['exchange_rate'] = 'Exchange rate must be greater than zero.';
        }
        $dec = (int) ($cur['decimal_places'] ?? 2);
        $num = static fn ($v) => (float) str_replace(',', '', (string) $v);

        $taxes = [];
        foreach (Database::all('SELECT id, rate FROM tax_rates WHERE company_id = ?', [$cid]) as $t) {
            $taxes[(int) $t['id']] = (float) $t['rate'];
        }
        $lines = [];
        $itemIds = [];
        foreach ($rawLines as $i => $l) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $desc = trim((string) ($l['description'] ?? ''));
            $qty = $num($l['qty'] ?? 0);
            $price = $num($l['unit_price'] ?? 0);
            if (!$itemId && $desc === '' && $qty == 0.0 && $price == 0.0) {
                continue;
            }
            if (!$itemId && $desc === '') {
                $errors["lines.$i.item_id"] = 'Select an item or type a description.';
            }
            if ($qty <= 0) {
                $errors["lines.$i.qty"] = 'Quantity must be more than zero.';
            }
            if ($price < 0) {
                $errors["lines.$i.unit_price"] = 'Price cannot be negative.';
            }
            $disc = max(0, min(100, $num($l['discount_pct'] ?? 0)));
            $taxId = (int) ($l['tax_rate_id'] ?? 0);
            if ($taxId && !isset($taxes[$taxId])) {
                $errors["lines.$i.tax_rate_id"] = 'Invalid tax.';
            }
            if ($itemId) {
                $itemIds[] = $itemId;
            }
            $lines[] = ['row' => $i, 'item_id' => $itemId ?: null, 'description' => mb_substr($desc, 0, 255) ?: null, 'qty' => $qty, 'unit_price' => $price,
                'discount_pct' => $disc, 'tax_rate_id' => $taxId ?: null, 'tax_pct' => $taxId ? ($taxes[$taxId] ?? 0) : 0];
        }
        if (!$lines) {
            $errors['lines'] = 'Add at least one line.';
        }
        if ($itemIds) {
            $valid = array_map('intval', array_column(Database::all('SELECT id FROM items WHERE company_id = ? AND id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')', [$cid, ...$itemIds]), 'id'));
            foreach ($lines as $l) {
                if ($l['item_id'] && !in_array($l['item_id'], $valid, true)) {
                    $errors["lines.{$l['row']}.item_id"] = 'Unknown item.';
                }
            }
        }
        if ($errors) {
            throw new ValidationException($errors, $errors['lines'] ?? 'Please correct the highlighted fields.');
        }
        [$lines, $t] = self::compute($lines, max(0, $num($h['freight'] ?? 0)), $dec);

        return Database::transaction(static function () use ($type, $cfg, $cid, $h, $lines, $t, $date, $due, $contact, $currency, $rate, $sourceType, $sourceId) {
            $id = (int) ($h['id'] ?? 0);
            $data = [
                'doc_date' => $date, 'due_date' => $due ?: null, 'contact_id' => $contact['id'],
                'reference' => mb_substr(trim((string) ($h['reference'] ?? '')), 0, 60) ?: null,
                'currency_code' => $currency, 'exchange_rate' => $rate,
                'place_of_supply' => ($h['place_of_supply'] ?? '') ?: $contact['place_of_supply'],
                'notes' => mb_substr(trim((string) ($h['notes'] ?? '')), 0, 1000) ?: null,
                'terms' => mb_substr(trim((string) ($h['terms'] ?? '')), 0, 1000) ?: null,
                'billing_address' => mb_substr(trim((string) ($h['billing_address'] ?? $contact['billing_address'] ?? '')), 0, 500) ?: null,
                'shipping_address' => mb_substr(trim((string) ($h['shipping_address'] ?? $contact['shipping_address'] ?? '')), 0, 500) ?: null,
                'total_base' => round($t['total'] * $rate, 2),
            ] + $t;
            if ($id) {
                $doc = Database::one('SELECT * FROM documents WHERE id = ? AND company_id = ? AND doc_type = ? FOR UPDATE', [$id, $cid, $type]);
                if (!$doc || $doc['status'] !== 'draft') {
                    throw new ValidationException([], 'Only drafts can be edited.');
                }
                Database::update('documents', $data, 'id = ?', [$id]);
                Database::query('DELETE FROM document_lines WHERE document_id = ?', [$id]);
                Audit::log($type, $id, 'update', $doc, $data);
            } else {
                $id = Database::insert('documents', $data + [
                    'company_id' => $cid, 'doc_type' => $type, 'number' => NumberSeries::next($cid, $cfg['series']), 'status' => 'draft',
                    'source_type' => $sourceType, 'source_id' => $sourceId, 'created_by' => Auth::instance()->id(),
                ]);
                Audit::log($type, $id, 'create', null, $data);
            }
            foreach ($lines as $n => $l) {
                unset($l['row']);
                Database::insert('document_lines', $l + ['document_id' => $id, 'company_id' => $cid, 'line_no' => $n + 1]);
            }
            return $id;
        });
    }

    // ------------------------------------------------------------------ taxes

    /** Ledger(s) for a tax line: GST is split into CGST+SGST (intra-state) or IGST (inter-state). */
    private static function taxSplit(int $cid, ?int $taxRateId, string $dir, bool $intra): array
    {
        $company = Auth::instance()->company();
        $tr = $taxRateId ? Database::one('SELECT * FROM tax_rates WHERE id = ? AND company_id = ?', [$taxRateId, $cid]) : null;
        if ($company['tax_regime'] === 'IN_GST' && (!$tr || $tr['tax_type'] === 'GST')) {
            $codes = $dir === 'output' ? ['2131', '2132', '2133'] : ['2134', '2135', '2136'];
            $c = Accounts::byCode($cid, $codes[0]);
            $s = Accounts::byCode($cid, $codes[1]);
            $i = Accounts::byCode($cid, $codes[2]);
            if ($intra && $c && $s) {
                return [[$c, 0.5, 'CGST'], [$s, 0.5, 'SGST']];
            }
            if ($i) {
                return [[$i, 1.0, 'IGST']];
            }
        }
        $acc = $tr ? ($dir === 'output' ? $tr['output_account_id'] : $tr['input_account_id']) : null;
        return [[$acc ? (int) $acc : Accounts::bySubType($cid, $dir === 'output' ? 'tax_output' : 'tax_input'), 1.0, $company['tax_regime'] === 'UAE_VAT' ? 'VAT' : 'Tax']];
    }

    public static function isIntraState(array $doc): bool
    {
        $company = Auth::instance()->company();
        return !$doc['place_of_supply'] || !$company['state_code'] || $doc['place_of_supply'] === $company['state_code'];
    }

    /** Tax summary rows for printing: [label, rate %, amount] */
    public static function taxSummary(array $doc): array
    {
        $company = Auth::instance()->company();
        $by = [];
        foreach ($doc['lines'] as $l) {
            if ((float) $l['tax_amount'] == 0.0 && (float) $l['tax_pct'] == 0.0) {
                continue;
            }
            $k = (string) (float) $l['tax_pct'];
            $by[$k] = ($by[$k] ?? 0) + (float) $l['tax_amount'];
        }
        $out = [];
        foreach ($by as $pct => $amt) {
            if ($company['tax_regime'] === 'IN_GST') {
                if (self::isIntraState($doc)) {
                    $out[] = ['CGST', $pct / 2, round($amt / 2, 2)];
                    $out[] = ['SGST', $pct / 2, $amt - round($amt / 2, 2)];
                } else {
                    $out[] = ['IGST', (float) $pct, $amt];
                }
            } else {
                $out[] = [$company['tax_regime'] === 'UAE_VAT' ? 'VAT' : 'Tax', (float) $pct, $amt];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ posting helpers

    private static function journal(array $doc, string $sourceType, array $lines, string $narration, ?string $date = null): int
    {
        $company = Auth::instance()->company();
        $merged = [];
        foreach ($lines as [$acc, $dr, $cr, $desc]) {
            $dr = round($dr, 2);
            $cr = round($cr, 2);
            if ($dr == 0.0 && $cr == 0.0) {
                continue;
            }
            $merged[] = ['account_id' => $acc, 'debit' => $dr, 'credit' => $cr, 'description' => $desc];
        }
        // absorb rounding so the voucher balances exactly
        $diff = round(array_sum(array_column($merged, 'debit')) - array_sum(array_column($merged, 'credit')), 2);
        if ($diff != 0.0 && $merged) {
            foreach ($merged as &$m) {
                if ($diff > 0 && $m['credit'] > 0) {
                    $m['credit'] = round($m['credit'] + $diff, 2);
                    break;
                }
                if ($diff < 0 && $m['debit'] > 0) {
                    $m['debit'] = round($m['debit'] - $diff, 2);
                    break;
                }
            }
            unset($m);
        }
        return JournalService::save([
            'journal_date' => $date ?? $doc['doc_date'], 'reference' => $doc['number'], 'narration' => $narration,
            'currency_code' => $company['base_currency'], 'exchange_rate' => 1,
        ], $merged, true, $sourceType, (int) $doc['id']);
    }

    private static function itemAccount(int $cid, ?int $itemId, string $field, string $subType, string $code): int
    {
        if ($itemId && ($acc = Database::value("SELECT $field FROM items WHERE id = ? AND company_id = ?", [$itemId, $cid]))) {
            return (int) $acc;
        }
        return Accounts::bySubType($cid, $subType, $code);
    }

    // ------------------------------------------------------------------ invoice

    public static function postInvoice(int $id): void
    {
        Database::transaction(static function () use ($id) {
            $cid = self::cid();
            $d = self::find($id, 'invoice');
            if ($d['status'] !== 'draft') {
                throw new ValidationException([], 'Only draft invoices can be posted.');
            }
            Database::query('SELECT id FROM documents WHERE id = ? FOR UPDATE', [$id]);
            $rate = (float) $d['exchange_rate'];
            $intra = self::isIntraState($d);
            $jl = [[Accounts::bySubType($cid, 'receivable', '1130'), $d['total'] * $rate, 0, $d['contact_name']]];
            $cogs = 0.0;
            foreach ($d['lines'] as $l) {
                $jl[] = [self::itemAccount($cid, $l['item_id'] ? (int) $l['item_id'] : null, 'sales_account_id', 'sales', '4100'), 0, $l['line_total'] * $rate, $l['description'] ?: $l['item_name']];
                foreach (self::taxSplit($cid, $l['tax_rate_id'] ? (int) $l['tax_rate_id'] : null, 'output', $intra) as [$acc, $share, $lbl]) {
                    $jl[] = [$acc, 0, $l['tax_amount'] * $rate * $share, "$lbl {$l['tax_pct']}%"];
                }
                if ($l['item_id'] && (int) $l['track_inventory'] === 1) {
                    $cost = Stock::issue($cid, (int) $l['item_id'], (float) $l['qty'], $d['doc_date'], 'invoice', $id, $d['number'], $l['item_name']);
                    Database::update('document_lines', ['cost_total' => $cost], 'id = ?', [$l['id']]);
                    $cogs += $cost;
                }
            }
            if ((float) $d['freight'] > 0) {
                $jl[] = [Accounts::byCode($cid, '4300') ?? Accounts::bySubType($cid, 'sales', '4100'), 0, $d['freight'] * $rate, 'Delivery charges'];
            }
            if ($cogs > 0) {
                $jl[] = [Accounts::bySubType($cid, 'cogs', '5100'), $cogs, 0, 'Cost of goods sold'];
                $jl[] = [Accounts::bySubType($cid, 'stock', '1140'), 0, $cogs, 'Inventory issued'];
            }
            $jid = self::journal($d, 'invoice', $jl, 'Invoice ' . $d['number'] . ' - ' . $d['contact_name']);
            Database::update('documents', ['status' => 'posted', 'journal_id' => $jid, 'posted_by' => Auth::instance()->id(), 'posted_at' => now_utc()], 'id = ?', [$id]);
            Audit::log('invoice', $id, 'post');
        });
    }

    /** Payment received against an invoice (or refund paid against a credit note) */
    public static function recordPayment(int $id, float $amount, int $accountId, string $date, ?string $ref = null): void
    {
        Database::transaction(static function () use ($id, $amount, $accountId, $date, $ref) {
            $cid = self::cid();
            $d = self::find($id);
            if (!in_array($d['doc_type'], ['invoice', 'credit_note'], true) || !in_array($d['status'], ['posted', 'partly_paid'], true)) {
                throw new ValidationException([], 'Payments can be recorded only on posted, unpaid documents.');
            }
            $due = round((float) $d['total'] - (float) $d['amount_paid'], 2);
            if ($amount <= 0 || $amount - $due > 0.005) {
                throw new ValidationException(['amount' => 'Amount must be between 0 and ' . number_format($due, 2) . '.']);
            }
            if (!Database::value("SELECT id FROM accounts WHERE id = ? AND company_id = ? AND is_group = 0 AND sub_type IN ('cash','bank')", [$accountId, $cid])) {
                throw new ValidationException(['account_id' => 'Select a cash or bank account.']);
            }
            $base = $amount * (float) $d['exchange_rate'];
            $ar = Accounts::bySubType($cid, 'receivable', '1130');
            $isRefund = $d['doc_type'] === 'credit_note';
            $lines = $isRefund
                ? [[$ar, $base, 0, $d['contact_name']], [$accountId, 0, $base, 'Refund paid']]
                : [[$accountId, $base, 0, 'Payment received' . ($ref ? " ($ref)" : '')], [$ar, 0, $base, $d['contact_name']]];
            self::journal($d, $isRefund ? 'refund' : 'receipt', $lines, ($isRefund ? 'Refund for ' : 'Payment for ') . $d['number'] . ($ref ? " - $ref" : ''), $date);
            $paid = round((float) $d['amount_paid'] + $amount, 2);
            Database::update('documents', ['amount_paid' => $paid, 'status' => $paid + 0.005 >= (float) $d['total'] ? 'paid' : 'partly_paid'], 'id = ?', [$id]);
            Audit::log($d['doc_type'], $id, 'payment', null, ['amount' => $amount]);
        });
    }

    public static function voidDocument(int $id): void
    {
        Database::transaction(static function () use ($id) {
            $cid = self::cid();
            $d = self::find($id);
            if ((float) $d['amount_paid'] > 0) {
                throw new ValidationException([], 'This document has payments. Reverse the payments before voiding.');
            }
            if ($d['doc_type'] === 'po' && Database::value('SELECT COUNT(*) FROM document_lines WHERE document_id = ? AND received_qty > 0', [$id])) {
                throw new ValidationException([], 'Goods were already received on this order. Close it instead.');
            }
            if ($d['journal_id']) {
                Database::update('journals', ['status' => 'void'], 'id = ? AND company_id = ?', [$d['journal_id'], $cid]);
            }
            Stock::reverse($cid, $d['doc_type'], $id, date('Y-m-d'));
            Database::update('documents', ['status' => 'void'], 'id = ?', [$id]);
            Audit::log($d['doc_type'], $id, 'void');
        });
    }

    // ------------------------------------------------------------------ quote

    public static function convertQuote(int $id): int
    {
        return Database::transaction(static function () use ($id) {
            $q = self::find($id, 'quote');
            if (in_array($q['status'], ['converted', 'void', 'declined'], true)) {
                throw new ValidationException([], 'This quote cannot be converted.');
            }
            $lines = array_map(static fn ($l) => ['item_id' => $l['item_id'], 'description' => $l['description'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'discount_pct' => $l['discount_pct'], 'tax_rate_id' => $l['tax_rate_id']], $q['lines']);
            $inv = self::save('invoice', [
                'doc_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+' . (int) Database::value('SELECT payment_terms FROM contacts WHERE id = ?', [$q['contact_id']]) . ' days')),
                'contact_id' => $q['contact_id'], 'reference' => $q['number'], 'currency_code' => $q['currency_code'], 'exchange_rate' => $q['exchange_rate'],
                'place_of_supply' => $q['place_of_supply'], 'notes' => $q['notes'], 'terms' => $q['terms'], 'freight' => $q['freight'],
                'billing_address' => $q['billing_address'], 'shipping_address' => $q['shipping_address'],
            ], $lines, 'quote', $id);
            Database::update('documents', ['status' => 'converted'], 'id = ?', [$id]);
            return $inv;
        });
    }

    // ------------------------------------------------------------------ purchase order receipt (GRN)

    /**
     * Receive goods against a PO. Freight is spread over the received lines by value and added to
     * the batch cost (landed cost). Optionally updates the items' cost and selling prices.
     * @param array $receive [line_id => qty]  @param array $newPrices [item_id => selling price]
     */
    public static function receivePo(int $id, array $receive, float $freight, string $date, ?string $billRef, array $newPrices = []): void
    {
        Database::transaction(static function () use ($id, $receive, $freight, $date, $billRef, $newPrices) {
            $cid = self::cid();
            $d = self::find($id, 'po');
            if (!in_array($d['status'], ['open', 'partial'], true)) {
                throw new ValidationException([], 'Approve the purchase order before receiving goods.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new ValidationException(['receipt_date' => 'Enter a valid date.']);
            }
            $rate = (float) $d['exchange_rate'];
            $rows = [];
            $value = 0.0;
            foreach ($d['lines'] as $l) {
                $q = (float) str_replace(',', '', (string) ($receive[$l['id']] ?? 0));
                $open = (float) $l['qty'] - (float) $l['received_qty'];
                if ($q <= 0) {
                    continue;
                }
                if ($q - $open > 0.00001) {
                    throw new ValidationException(["receive.{$l['id']}" => 'More than the open quantity (' . rtrim(rtrim(number_format($open, 3), '0'), '.') . ').']);
                }
                $net = (float) $l['line_total'] / (float) $l['qty']; // unit price after discount
                $rows[] = [$l, $q, $net];
                $value += $q * $net;
            }
            if (!$rows) {
                throw new ValidationException([], 'Enter the quantity received on at least one line.');
            }
            $jl = [];
            $ap = Accounts::bySubType($cid, 'payable', '2110');
            $intra = self::isIntraState($d);
            $total = 0.0;
            $ref = $d['number'] . ($billRef ? " / $billRef" : '');
            foreach ($rows as [$l, $q, $net]) {
                $share = $value > 0 ? ($q * $net) / $value * $freight : 0; // landed cost share (document currency)
                $lineValue = ($q * $net + $share) * $rate;
                $tax = (float) $l['tax_amount'] * ($q / (float) $l['qty']) * $rate;
                if ($l['item_id'] && (int) $l['track_inventory'] === 1) {
                    $unitCost = $lineValue / $q;
                    Stock::receive($cid, (int) $l['item_id'], $q, $unitCost, $date, 'receipt', $id, $ref);
                    Database::update('items', ['purchase_price' => round($unitCost, 4)], 'id = ? AND company_id = ?', [$l['item_id'], $cid]);
                    $jl[] = [Accounts::bySubType($cid, 'stock', '1140'), $lineValue, 0, ($l['item_name'] ?? 'Goods') . ' × ' . $q];
                } else {
                    $jl[] = [self::itemAccount($cid, $l['item_id'] ? (int) $l['item_id'] : null, 'purchase_account_id', 'purchase', '5200'), $lineValue, 0, $l['description'] ?: ($l['item_name'] ?? 'Purchase')];
                }
                foreach (self::taxSplit($cid, $l['tax_rate_id'] ? (int) $l['tax_rate_id'] : null, 'input', $intra) as [$acc, $sh, $lbl]) {
                    $jl[] = [$acc, $tax * $sh, 0, "Input $lbl {$l['tax_pct']}%"];
                }
                $total += $lineValue + $tax;
                Database::query('UPDATE document_lines SET received_qty = received_qty + ? WHERE id = ?', [$q, $l['id']]);
            }
            $jl[] = [$ap, 0, $total, $d['contact_name'] . ($billRef ? " bill $billRef" : '')];
            self::journal($d, 'receipt', $jl, 'Goods received ' . $ref . ($freight > 0 ? ' (incl. freight ' . number_format($freight, 2) . ')' : ''), $date);
            foreach ($newPrices as $itemId => $price) {
                if ($price !== '' && is_numeric($price) && (float) $price >= 0) {
                    Database::update('items', ['sales_price' => (float) $price], 'id = ? AND company_id = ?', [(int) $itemId, $cid]);
                }
            }
            $open = (float) Database::value('SELECT SUM(GREATEST(qty - received_qty, 0)) FROM document_lines WHERE document_id = ?', [$id]);
            Database::update('documents', ['status' => $open > 0.00001 ? 'partial' : 'received'], 'id = ?', [$id]);
            Audit::log('po', $id, 'receive', null, ['freight' => $freight, 'bill' => $billRef]);
        });
    }

    // ------------------------------------------------------------------ credit note (returns)

    /**
     * Posted credit note for returned goods: reverses revenue + tax, puts the goods back into
     * stock at their original cost. @param array $lines [[item_id, description, qty, unit_price, tax_rate_id, unit_cost]]
     */
    public static function creditNote(int $contactId, array $lines, string $reference, ?string $placeOfSupply, string $sourceType, ?int $sourceId): int
    {
        return Database::transaction(static function () use ($contactId, $lines, $reference, $placeOfSupply, $sourceType, $sourceId) {
            $cid = self::cid();
            $costs = [];
            $raw = [];
            foreach ($lines as $l) {
                $raw[] = ['item_id' => $l['item_id'], 'description' => $l['description'], 'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'discount_pct' => 0, 'tax_rate_id' => $l['tax_rate_id']];
                $costs[] = (float) $l['unit_cost'];
            }
            $id = self::save('credit_note', ['doc_date' => date('Y-m-d'), 'contact_id' => $contactId, 'reference' => $reference, 'place_of_supply' => $placeOfSupply], $raw, $sourceType, $sourceId);
            $d = self::find($id);
            $intra = self::isIntraState($d);
            $jl = [[Accounts::bySubType($cid, 'receivable', '1130'), 0, (float) $d['total'], $d['contact_name']]];
            $stockValue = 0.0;
            foreach ($d['lines'] as $i => $l) {
                $jl[] = [self::itemAccount($cid, $l['item_id'] ? (int) $l['item_id'] : null, 'sales_account_id', 'sales', '4100'), (float) $l['line_total'], 0, 'Return: ' . ($l['item_name'] ?? '')];
                foreach (self::taxSplit($cid, $l['tax_rate_id'] ? (int) $l['tax_rate_id'] : null, 'output', $intra) as [$acc, $sh, $lbl]) {
                    $jl[] = [$acc, (float) $l['tax_amount'] * $sh, 0, "$lbl reversal"];
                }
                if ($l['item_id'] && (int) $l['track_inventory'] === 1 && $costs[$i] > 0) {
                    Stock::receive($cid, (int) $l['item_id'], (float) $l['qty'], $costs[$i], $d['doc_date'], 'return', $id, $d['number']);
                    $stockValue += $costs[$i] * (float) $l['qty'];
                }
            }
            if ($stockValue > 0) {
                $jl[] = [Accounts::bySubType($cid, 'stock', '1140'), $stockValue, 0, 'Returned goods back in stock'];
                $jl[] = [Accounts::bySubType($cid, 'cogs', '5100'), 0, $stockValue, 'Cost of returned goods'];
            }
            $jid = self::journal($d, 'credit_note', $jl, 'Credit note ' . $d['number'] . ' for ' . $reference);
            Database::update('documents', ['status' => 'posted', 'journal_id' => $jid, 'posted_by' => Auth::instance()->id(), 'posted_at' => now_utc()], 'id = ?', [$id]);
            return $id;
        });
    }
}
