<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;

/**
 * FIFO stock batches. Every receipt creates a batch with its own (landed) unit cost;
 * every issue consumes the oldest batches first and returns the cost of goods.
 * Must run inside the caller's transaction (batches are locked FOR UPDATE).
 */
final class Stock
{
    /** On-hand quantity per item: [item_id => qty] */
    public static function onHand(int $companyId, array $itemIds = []): array
    {
        $sql = 'SELECT item_id, SUM(qty_remaining) q FROM stock_batches WHERE company_id = ?';
        $p = [$companyId];
        if ($itemIds) {
            $sql .= ' AND item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')';
            array_push($p, ...array_map('intval', $itemIds));
        }
        $out = [];
        foreach (Database::all($sql . ' GROUP BY item_id', $p) as $r) {
            $out[(int) $r['item_id']] = (float) $r['q'];
        }
        return $out;
    }

    /** Quantity held by online orders that are not yet invoiced (shipped) */
    public static function reserved(int $companyId, array $itemIds = []): array
    {
        $sql = "SELECT l.item_id, SUM(l.qty) q FROM shop_order_lines l JOIN shop_orders o ON o.id = l.order_id
                 WHERE o.company_id = ? AND o.status IN ('placed','confirmed','packed') AND o.invoice_id IS NULL";
        $p = [$companyId];
        if ($itemIds) {
            $sql .= ' AND l.item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')';
            array_push($p, ...array_map('intval', $itemIds));
        }
        $out = [];
        foreach (Database::all($sql . ' GROUP BY l.item_id', $p) as $r) {
            $out[(int) $r['item_id']] = (float) $r['q'];
        }
        return $out;
    }

    public static function receive(int $companyId, int $itemId, float $qty, float $unitCost, string $date, string $sourceType, ?int $sourceId, ?string $ref = null, ?float $mrp = null, ?string $batchNo = null): int
    {
        if ($qty <= 0) {
            return 0;
        }
        $batch = Database::insert('stock_batches', [
            'company_id' => $companyId, 'item_id' => $itemId, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'batch_no' => $batchNo, 'qty_in' => $qty, 'qty_remaining' => $qty, 'unit_cost' => round($unitCost, 4),
            'mrp' => $mrp, 'received_at' => $date,
        ]);
        Database::insert('stock_moves', [
            'company_id' => $companyId, 'item_id' => $itemId, 'batch_id' => $batch, 'move_date' => $date, 'qty' => $qty,
            'unit_cost' => round($unitCost, 4), 'value' => round($qty * $unitCost, 4), 'source_type' => $sourceType,
            'source_id' => $sourceId, 'reference' => $ref, 'created_by' => auth()->id(),
        ]);
        return $batch;
    }

    /**
     * Issue stock oldest-first. @return float total cost of the issued quantity
     * @throws ValidationException when there is not enough stock
     */
    public static function issue(int $companyId, int $itemId, float $qty, string $date, string $sourceType, ?int $sourceId, ?string $ref = null, string $itemLabel = ''): float
    {
        if ($qty <= 0) {
            return 0.0;
        }
        $batches = Database::all(
            'SELECT id, qty_remaining, unit_cost FROM stock_batches WHERE company_id = ? AND item_id = ? AND qty_remaining > 0 ORDER BY received_at, id FOR UPDATE',
            [$companyId, $itemId]
        );
        $available = array_sum(array_map(static fn ($b) => (float) $b['qty_remaining'], $batches));
        if ($available + 0.00001 < $qty) {
            throw new ValidationException([], sprintf('Not enough stock for %s: %s available, %s needed.', $itemLabel ?: "item #$itemId", rtrim(rtrim(number_format($available, 3), '0'), '.') ?: '0', rtrim(rtrim(number_format($qty, 3), '0'), '.')));
        }
        $left = $qty;
        $cost = 0.0;
        foreach ($batches as $b) {
            if ($left <= 0.00001) {
                break;
            }
            $take = min($left, (float) $b['qty_remaining']);
            Database::query('UPDATE stock_batches SET qty_remaining = qty_remaining - ? WHERE id = ?', [$take, $b['id']]);
            Database::insert('stock_moves', [
                'company_id' => $companyId, 'item_id' => $itemId, 'batch_id' => $b['id'], 'move_date' => $date, 'qty' => -$take,
                'unit_cost' => $b['unit_cost'], 'value' => -round($take * (float) $b['unit_cost'], 4), 'source_type' => $sourceType,
                'source_id' => $sourceId, 'reference' => $ref, 'created_by' => auth()->id(),
            ]);
            $cost += $take * (float) $b['unit_cost'];
            $left -= $take;
        }
        return round($cost, 4);
    }

    /** Put back everything a document issued (void / cancel): reverses its moves into the same batches */
    public static function reverse(int $companyId, string $sourceType, int $sourceId, string $date): void
    {
        $moves = Database::all('SELECT * FROM stock_moves WHERE company_id = ? AND source_type = ? AND source_id = ? AND qty < 0', [$companyId, $sourceType, $sourceId]);
        foreach ($moves as $m) {
            Database::query('UPDATE stock_batches SET qty_remaining = qty_remaining + ? WHERE id = ?', [-(float) $m['qty'], $m['batch_id']]);
            Database::insert('stock_moves', [
                'company_id' => $companyId, 'item_id' => $m['item_id'], 'batch_id' => $m['batch_id'], 'move_date' => $date, 'qty' => -(float) $m['qty'],
                'unit_cost' => $m['unit_cost'], 'value' => -(float) $m['value'], 'source_type' => $sourceType . '_void', 'source_id' => $sourceId,
                'reference' => 'Reversal', 'created_by' => auth()->id(),
            ]);
        }
    }
}
