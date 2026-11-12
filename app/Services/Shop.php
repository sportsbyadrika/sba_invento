<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;

/**
 * Online shop domain logic: catalogue, availability, cart, orders, delivery stages, returns, reviews.
 * Prices in the ERP are tax-exclusive; the shop shows tax-inclusive prices to consumers.
 */
final class Shop
{
    public const STAGES = [
        'placed' => ['Order placed', 'amber'],
        'confirmed' => ['Confirmed', 'blue'],
        'packed' => ['Packed', 'cyan'],
        'shipped' => ['Shipped', 'indigo'],
        'out_for_delivery' => ['Out for delivery', 'violet'],
        'delivered' => ['Delivered', 'green'],
        'cancelled' => ['Cancelled', 'slate'],
    ];
    public const FLOW = ['placed', 'confirmed', 'packed', 'shipped', 'out_for_delivery', 'delivered'];
    public const RETURN_STATUS = [
        'requested' => ['Requested', 'amber'], 'approved' => ['Approved - awaiting goods', 'blue'], 'rejected' => ['Rejected', 'slate'],
        'received' => ['Goods received', 'cyan'], 'refunded' => ['Refunded', 'green'],
    ];
    public const RETURN_REASONS = ['damaged' => 'Damaged / defective', 'wrong_item' => 'Wrong item delivered', 'not_as_described' => 'Not as described', 'changed_mind' => 'No longer needed', 'other' => 'Other'];
    public const PHONE_CODES = ['+971' => 'UAE +971', '+91' => 'India +91', '+966' => 'Saudi +966', '+968' => 'Oman +968', '+974' => 'Qatar +974', '+973' => 'Bahrain +973', '+965' => 'Kuwait +965', '+44' => 'UK +44', '+1' => 'USA/Canada +1'];

    // ------------------------------------------------------------------ store

    public static function store(string $slug): ?array
    {
        $s = Database::one(
            "SELECT s.*, c.name AS company_name, c.base_currency, c.tax_regime, c.status AS company_status, c.country_code, c.state_code
               FROM shop_settings s JOIN companies c ON c.id = s.company_id WHERE s.slug = ? AND s.enabled = 1",
            [strtolower($slug)]
        );
        return $s && $s['company_status'] === 'active' ? $s : null;
    }

    public static function settings(int $cid): array
    {
        $s = Database::one('SELECT * FROM shop_settings WHERE company_id = ?', [$cid]);
        if (!$s) {
            $c = Database::one('SELECT name, code, email, phone FROM companies WHERE id = ?', [$cid]);
            $s = ['company_id' => $cid, 'enabled' => 0, 'slug' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $c['code'] ?? 'store')), 'store_name' => $c['name'] ?? 'Store',
                'tagline' => null, 'contact_email' => $c['email'] ?? null, 'contact_phone' => $c['phone'] ?? null, 'delivery_fee' => 0, 'free_delivery_min' => null,
                'cod_enabled' => 1, 'cod_max' => null, 'cod_fee' => 0, 'return_days' => 7, 'low_stock_badge' => 5,
                'terms' => null, 'privacy' => null, 'returns_policy' => null, 'shipping_policy' => null];
        }
        return $s;
    }

    // ------------------------------------------------------------------ catalogue

    /** All published items with price (incl. tax), online quantity, rating and main image */
    public static function catalogue(int $cid, ?array $ids = null): array
    {
        $sql = "SELECT i.id, i.sku, i.name, i.slug, i.brand, i.category_id, c.name AS category, i.item_type, i.track_inventory,
                       COALESCE(i.online_price, i.sales_price) AS price_ex, i.mrp, i.online_stock_cap, i.min_order_qty, i.max_order_qty,
                       i.short_description, i.long_description, i.description, i.attributes, i.return_days, i.created_at, i.tax_rate_id,
                       COALESCE(t.rate, 0) AS tax_rate, u.code AS unit,
                       (SELECT im.thumb FROM item_images im WHERE im.item_id = i.id ORDER BY im.is_main DESC, im.sort_order, im.id LIMIT 1) AS thumb,
                       (SELECT im.file FROM item_images im WHERE im.item_id = i.id ORDER BY im.is_main DESC, im.sort_order, im.id LIMIT 1) AS image,
                       (SELECT AVG(r.rating) FROM shop_reviews r WHERE r.item_id = i.id AND r.status = 'approved') AS rating,
                       (SELECT COUNT(*) FROM shop_reviews r WHERE r.item_id = i.id AND r.status = 'approved') AS reviews
                  FROM items i LEFT JOIN item_categories c ON c.id = i.category_id LEFT JOIN tax_rates t ON t.id = i.tax_rate_id LEFT JOIN units u ON u.id = i.unit_id
                 WHERE i.company_id = ? AND i.publish_online = 1 AND i.is_active = 1";
        $p = [$cid];
        if ($ids !== null) {
            if (!$ids) {
                return [];
            }
            $sql .= ' AND i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($p, ...array_map('intval', $ids));
        }
        $rows = Database::all($sql, $p);
        $itemIds = array_map(static fn ($r) => (int) $r['id'], $rows);
        $onHand = Stock::onHand($cid, $itemIds);
        $reserved = Stock::reserved($cid, $itemIds);
        $dec = base_decimals();
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $r['price'] = round((float) $r['price_ex'] * (1 + (float) $r['tax_rate'] / 100), $dec);
            $r['mrp'] = $r['mrp'] !== null && (float) $r['mrp'] > $r['price'] ? (float) $r['mrp'] : null;
            if ((int) $r['track_inventory'] === 1) {
                $avail = max(0.0, ($onHand[$id] ?? 0) - ($reserved[$id] ?? 0));
                $r['available'] = $r['online_stock_cap'] !== null ? min($avail, (float) $r['online_stock_cap']) : $avail;
            } else {
                $r['available'] = null; // services: not limited
            }
            $r['in_stock'] = $r['available'] === null || $r['available'] >= max(1.0, (float) $r['min_order_qty']);
            $r['attrs'] = self::attributes((string) $r['attributes']);
            $r['url_slug'] = $id . '-' . ($r['slug'] ?: str_slug($r['name']));
            $out[$id] = $r;
        }
        return $out;
    }

    /** "Colour: Red" lines -> ['Colour' => 'Red'] */
    public static function attributes(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                if ($k !== '' && $v !== '') {
                    $out[mb_substr($k, 0, 40)] = mb_substr($v, 0, 80);
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ cart (session)

    public static function cart(int $cid): array
    {
        return array_filter((array) ($_SESSION['cart'][$cid] ?? []), static fn ($q) => $q > 0);
    }

    public static function cartSet(int $cid, int $itemId, float $qty): void
    {
        if ($qty <= 0) {
            unset($_SESSION['cart'][$cid][$itemId]);
        } else {
            $_SESSION['cart'][$cid][$itemId] = $qty;
        }
    }

    public static function cartClear(int $cid): void
    {
        unset($_SESSION['cart'][$cid]);
    }

    /** Priced cart with validation messages per line */
    public static function cartView(int $cid, array $settings): array
    {
        $cart = self::cart($cid);
        $items = self::catalogue($cid, array_keys($cart));
        $dec = base_decimals();
        $lines = [];
        $sub = $tax = $gross = 0.0;
        foreach ($cart as $id => $qty) {
            $it = $items[(int) $id] ?? null;
            if (!$it) {
                self::cartSet($cid, (int) $id, 0);
                continue;
            }
            $issue = null;
            if ($it['available'] !== null && $qty > $it['available']) {
                $issue = $it['available'] <= 0 ? 'Out of stock' : 'Only ' . (float) $it['available'] . ' available';
            }
            $ex = round((float) $it['price_ex'] * $qty, $dec);
            $t = round($ex * (float) $it['tax_rate'] / 100, $dec);
            $lines[] = ['item' => $it, 'qty' => (float) $qty, 'line_ex' => $ex, 'tax' => $t, 'line_total' => $ex + $t, 'issue' => $issue];
            $sub += $ex;
            $tax += $t;
            $gross += $ex + $t;
        }
        $fee = (float) $settings['delivery_fee'];
        if ($settings['free_delivery_min'] !== null && $gross >= (float) $settings['free_delivery_min']) {
            $fee = 0.0;
        }
        return ['lines' => $lines, 'subtotal' => round($sub, $dec), 'tax' => round($tax, $dec), 'items_total' => round($gross, $dec),
            'delivery_fee' => $lines ? round($fee, $dec) : 0.0, 'count' => array_sum(array_map(static fn ($l) => $l['qty'], $lines)),
            'valid' => $lines && !array_filter($lines, static fn ($l) => $l['issue'] !== null)];
    }

    // ------------------------------------------------------------------ orders

    public static function placeOrder(array $store, array $customer, array $address, string $method, ?string $note): int
    {
        $cid = (int) $store['company_id'];
        if ($method === 'epayment') {
            throw new ValidationException(['payment_method' => 'Online payment is coming soon. Please choose Cash on Delivery.']);
        }
        if ($method !== 'cod' || !$store['cod_enabled']) {
            throw new ValidationException(['payment_method' => 'Select a payment method.']);
        }
        return Database::transaction(static function () use ($store, $customer, $address, $method, $note, $cid) {
            $ids = array_keys(self::cart($cid));
            if ($ids) { // serialise concurrent orders for the same items
                Database::query('SELECT id FROM items WHERE id IN (' . implode(',', array_map('intval', $ids)) . ') FOR UPDATE');
            }
            $view = self::cartView($cid, $store);
            if (!$view['lines']) {
                throw new ValidationException([], 'Your cart is empty.');
            }
            if (!$view['valid']) {
                throw new ValidationException([], 'Some items are no longer available in the quantity you selected. Please review your cart.');
            }
            $cod = $method === 'cod' ? (float) $store['cod_fee'] : 0.0;
            $total = $view['items_total'] + $view['delivery_fee'] + $cod;
            if ($method === 'cod' && $store['cod_max'] !== null && $total > (float) $store['cod_max']) {
                throw new ValidationException(['payment_method' => 'Cash on delivery is available for orders up to ' . money($store['cod_max']) . '.']);
            }
            $id = Database::insert('shop_orders', [
                'company_id' => $cid, 'number' => NumberSeries::next($cid, 'shop_order'), 'customer_id' => $customer['id'], 'status' => 'placed',
                'payment_method' => $method, 'payment_status' => 'pending',
                'ship_name' => $address['name'], 'ship_phone' => $address['phone'], 'ship_label' => $address['label'],
                'ship_address' => trim($address['line1'] . "\n" . ($address['line2'] ?? '') . ($address['postal_code'] ? "\n" . $address['postal_code'] : '')),
                'ship_city' => $address['city'], 'ship_region' => $address['region'], 'ship_country' => $address['country'],
                'currency_code' => $store['base_currency'], 'subtotal' => $view['subtotal'], 'tax_total' => $view['tax'],
                'delivery_fee' => $view['delivery_fee'], 'cod_fee' => $cod, 'total' => $total,
                'customer_note' => $note ? mb_substr($note, 0, 500) : null, 'placed_at' => now_utc(),
            ]);
            foreach ($view['lines'] as $l) {
                $it = $l['item'];
                Database::insert('shop_order_lines', [
                    'order_id' => $id, 'item_id' => $it['id'], 'sku' => $it['sku'], 'name' => $it['name'], 'image' => $it['thumb'],
                    'qty' => $l['qty'], 'unit_price' => $it['price_ex'], 'tax_rate_id' => $it['tax_rate_id'], 'tax_pct' => $it['tax_rate'],
                    'tax_amount' => $l['tax'], 'line_total' => $l['line_ex'],
                ]);
            }
            self::event($id, 'placed', 'Order placed · ' . ($method === 'cod' ? 'Cash on delivery' : 'Online payment'), 'customer', (int) $customer['id']);
            self::cartClear($cid);
            return $id;
        });
    }

    public static function order(int $cid, int $id): ?array
    {
        $o = Database::one('SELECT o.*, c.name AS customer_name, c.email AS customer_email, c.phone_code, c.phone AS customer_phone FROM shop_orders o JOIN shop_customers c ON c.id = o.customer_id WHERE o.id = ? AND o.company_id = ?', [$id, $cid]);
        if (!$o) {
            return null;
        }
        $o['lines'] = Database::all('SELECT * FROM shop_order_lines WHERE order_id = ? ORDER BY id', [$id]);
        $o['events'] = Database::all('SELECT * FROM shop_order_events WHERE order_id = ? ORDER BY id', [$id]);
        $o['returns'] = Database::all('SELECT * FROM shop_returns WHERE order_id = ? ORDER BY id', [$id]);
        return $o;
    }

    public static function event(int $orderId, string $status, ?string $note, string $actor, ?int $actorId): void
    {
        Database::insert('shop_order_events', ['order_id' => $orderId, 'status' => $status, 'note' => $note ? mb_substr($note, 0, 300) : null, 'actor' => $actor, 'actor_id' => $actorId, 'created_at' => now_utc()]);
    }

    /** Linked accounting customer (created on first invoice) */
    public static function contactFor(array $order): int
    {
        $cid = (int) $order['company_id'];
        $cust = Database::one('SELECT * FROM shop_customers WHERE id = ?', [$order['customer_id']]);
        if ($cust['contact_id'] && Database::value('SELECT id FROM contacts WHERE id = ? AND company_id = ?', [$cust['contact_id'], $cid])) {
            return (int) $cust['contact_id'];
        }
        $contact = Database::insert('contacts', [
            'company_id' => $cid, 'contact_type' => 'customer', 'code' => NumberSeries::next($cid, 'customer'), 'name' => $cust['name'],
            'email' => $cust['email'], 'phone' => $cust['phone_code'] . ' ' . $cust['phone'], 'tax_treatment' => null,
            'place_of_supply' => $order['ship_region'] ?: null, 'currency_code' => $order['currency_code'],
            'billing_address' => $order['ship_address'] . "\n" . $order['ship_city'],
        ]);
        Database::update('shop_customers', ['contact_id' => $contact], 'id = ?', [$cust['id']]);
        return $contact;
    }

    /**
     * Staff moves an order to the next stage. Shipping creates and posts the tax invoice (stock is
     * issued FIFO); delivery of a COD order records the cash received.
     */
    public static function advance(int $orderId, string $to, array $data = []): void
    {
        Database::transaction(static function () use ($orderId, $to, $data) {
            $cid = (int) Auth::instance()->companyId();
            $o = self::order($cid, $orderId) ?? throw new ValidationException([], 'Order not found.');
            Database::query('SELECT id FROM shop_orders WHERE id = ? FOR UPDATE', [$orderId]);
            $from = $o['status'];
            $uid = Auth::instance()->id();
            if ($to === 'cancelled') {
                if (!in_array($from, ['placed', 'confirmed', 'packed'], true)) {
                    throw new ValidationException([], 'Only orders that are not yet shipped can be cancelled.');
                }
                Database::update('shop_orders', ['status' => 'cancelled', 'cancelled_at' => now_utc()], 'id = ?', [$orderId]);
                self::event($orderId, 'cancelled', $data['note'] ?? 'Cancelled by store', 'staff', $uid);
                self::notify($o, 'cancelled');
                return;
            }
            $iFrom = array_search($from, self::FLOW, true);
            $iTo = array_search($to, self::FLOW, true);
            if ($iFrom === false || $iTo === false || $iTo <= $iFrom) {
                throw new ValidationException([], 'This order cannot move from ' . self::STAGES[$from][0] . ' to ' . (self::STAGES[$to][0] ?? $to) . '.');
            }
            $update = ['status' => $to];
            $note = trim((string) ($data['note'] ?? '')) ?: null;
            if ($iTo >= array_search('shipped', self::FLOW, true) && !$o['invoice_id']) {
                // Tax invoice at dispatch: stock leaves the warehouse now.
                $lines = array_map(static fn ($l) => ['item_id' => $l['item_id'], 'description' => null, 'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'discount_pct' => 0, 'tax_rate_id' => $l['tax_rate_id']], $o['lines']);
                $inv = DocumentService::save('invoice', [
                    'doc_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'), 'contact_id' => self::contactFor($o), 'reference' => $o['number'],
                    'currency_code' => $o['currency_code'], 'place_of_supply' => $o['ship_region'], 'freight' => (float) $o['delivery_fee'] + (float) $o['cod_fee'],
                    'billing_address' => $o['ship_name'] . "\n" . $o['ship_address'] . "\n" . $o['ship_city'],
                    'shipping_address' => $o['ship_name'] . ' · ' . $o['ship_phone'] . "\n" . $o['ship_address'] . "\n" . $o['ship_city'],
                    'notes' => 'Online order ' . $o['number'],
                ], $lines, 'shop', $orderId);
                DocumentService::postInvoice($inv);
                $update['invoice_id'] = $inv;
            }
            if ($to === 'shipped' || isset($data['courier'])) {
                $update['courier'] = mb_substr(trim((string) ($data['courier'] ?? $o['courier'] ?? '')), 0, 60) ?: null;
                $update['tracking_no'] = mb_substr(trim((string) ($data['tracking_no'] ?? $o['tracking_no'] ?? '')), 0, 60) ?: null;
                if ($to === 'shipped' && $update['courier']) {
                    $note = trim(($note ? "$note · " : '') . $update['courier'] . ($update['tracking_no'] ? ' #' . $update['tracking_no'] : ''));
                }
            }
            if ($to === 'delivered') {
                $update['delivered_at'] = now_utc();
                $invId = $update['invoice_id'] ?? $o['invoice_id'];
                if ($o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid') {
                    $acc = (int) ($data['account_id'] ?? 0) ?: Accounts::bySubType($cid, 'cash', '1110');
                    $doc = Database::one('SELECT total, amount_paid FROM documents WHERE id = ?', [$invId]);
                    $due = round((float) $doc['total'] - (float) $doc['amount_paid'], 2);
                    if ($due > 0) {
                        DocumentService::recordPayment((int) $invId, $due, $acc, date('Y-m-d'), 'COD ' . $o['number']);
                    }
                    $update['payment_status'] = 'paid';
                    $note = trim(($note ? "$note · " : '') . 'Cash collected');
                }
            }
            Database::update('shop_orders', $update, 'id = ?', [$orderId]);
            // record skipped intermediate stages too, so the customer timeline is complete
            for ($i = $iFrom + 1; $i <= $iTo; $i++) {
                self::event($orderId, self::FLOW[$i], $i === $iTo ? $note : null, 'staff', $uid);
            }
            self::notify($o, $to);
        });
    }

    /** E-mail the customer about a stage change (best effort) */
    public static function notify(array $o, string $status): void
    {
        $store = Database::one('SELECT store_name, slug FROM shop_settings WHERE company_id = ?', [$o['company_id']]);
        if (!$store || empty($o['customer_email'])) {
            return;
        }
        $label = self::STAGES[$status][0] ?? ucfirst($status);
        $link = rtrim((string) config('app.url'), '/') . url('/store/' . $store['slug'] . '/account/orders/' . $o['number']);
        Mailer::send($o['customer_email'], "{$store['store_name']}: order {$o['number']} - $label",
            '<p>Hello ' . e($o['customer_name']) . ',</p><p>Your order <b>' . e($o['number']) . '</b> is now: <b>' . e($label) . '</b>.</p><p><a href="' . e($link) . '">View your order</a></p><p>' . e($store['store_name']) . '</p>');
    }

    // ------------------------------------------------------------------ returns

    public static function returnWindowOpen(array $o, array $store, array $line = []): bool
    {
        if ($o['status'] !== 'delivered' || !$o['delivered_at']) {
            return false;
        }
        $days = (int) ($line['return_days'] ?? 0) ?: (int) $store['return_days'];
        return strtotime($o['delivered_at'] . ' UTC') + $days * 86400 >= time();
    }

    public static function requestReturn(array $store, array $customer, array $o, array $qtys, string $reason, string $details): int
    {
        if (!self::returnWindowOpen($o, $store)) {
            throw new ValidationException([], 'The return window for this order has closed.');
        }
        if (!isset(self::RETURN_REASONS[$reason])) {
            throw new ValidationException(['reason' => 'Select a reason.']);
        }
        $rows = [];
        foreach ($o['lines'] as $l) {
            $q = (float) ($qtys[$l['id']] ?? 0);
            if ($q <= 0) {
                continue;
            }
            $pending = (float) Database::value("SELECT COALESCE(SUM(rl.qty),0) FROM shop_return_lines rl JOIN shop_returns r ON r.id = rl.return_id WHERE rl.order_line_id = ? AND r.status IN ('requested','approved','received')", [$l['id']]);
            $max = (float) $l['qty'] - (float) $l['returned_qty'] - $pending;
            if ($q > $max + 0.00001) {
                throw new ValidationException([], 'You can return at most ' . (float) $max . ' of ' . $l['name'] . '.');
            }
            $unit = ((float) $l['line_total'] + (float) $l['tax_amount']) / (float) $l['qty'];
            $rows[] = [$l, $q, round($unit * $q, 2)];
        }
        if (!$rows) {
            throw new ValidationException([], 'Select the item(s) and quantity to return.');
        }
        return Database::transaction(static function () use ($store, $customer, $o, $rows, $reason, $details) {
            $cid = (int) $store['company_id'];
            $id = Database::insert('shop_returns', [
                'company_id' => $cid, 'number' => NumberSeries::next($cid, 'shop_return'), 'order_id' => $o['id'], 'customer_id' => $customer['id'],
                'status' => 'requested', 'reason' => $reason, 'details' => mb_substr($details, 0, 1000) ?: null,
                'refund_amount' => array_sum(array_column($rows, 2)), 'created_at' => now_utc(),
            ]);
            foreach ($rows as [$l, $q, $amt]) {
                Database::insert('shop_return_lines', ['return_id' => $id, 'order_line_id' => $l['id'], 'item_id' => $l['item_id'], 'qty' => $q, 'amount' => $amt]);
            }
            self::event((int) $o['id'], 'return_requested', 'Return requested: ' . self::RETURN_REASONS[$reason], 'customer', (int) $customer['id']);
            return $id;
        });
    }

    /** Staff: approve / reject / receive goods (credit note + stock back) / refund */
    public static function processReturn(int $id, string $action, array $data = []): void
    {
        Database::transaction(static function () use ($id, $action, $data) {
            $cid = (int) Auth::instance()->companyId();
            $r = Database::one('SELECT * FROM shop_returns WHERE id = ? AND company_id = ? FOR UPDATE', [$id, $cid]) ?? throw new ValidationException([], 'Return not found.');
            $o = self::order($cid, (int) $r['order_id']);
            $note = trim((string) ($data['note'] ?? '')) ?: null;
            $uid = Auth::instance()->id();
            switch ($action) {
                case 'approve':
                case 'reject':
                    if ($r['status'] !== 'requested') {
                        throw new ValidationException([], 'Only new requests can be approved or rejected.');
                    }
                    $st = $action === 'approve' ? 'approved' : 'rejected';
                    Database::update('shop_returns', ['status' => $st, 'staff_note' => $note], 'id = ?', [$id]);
                    self::event((int) $o['id'], 'return_' . $st, $note ?? ($st === 'approved' ? 'Return approved - we will collect the item(s)' : 'Return rejected'), 'staff', $uid);
                    break;
                case 'receive':
                    if ($r['status'] !== 'approved') {
                        throw new ValidationException([], 'Approve the return before receiving the goods.');
                    }
                    $lines = [];
                    foreach (Database::all('SELECT rl.*, ol.unit_price, ol.tax_rate_id, ol.name, ol.qty AS order_qty FROM shop_return_lines rl JOIN shop_order_lines ol ON ol.id = rl.order_line_id WHERE rl.return_id = ?', [$id]) as $rl) {
                        // original FIFO cost of the goods sold on the invoice
                        $cost = (float) Database::value('SELECT CASE WHEN qty > 0 THEN cost_total / qty ELSE 0 END FROM document_lines WHERE document_id = ? AND item_id = ? LIMIT 1', [$o['invoice_id'], $rl['item_id']]);
                        $restock = !empty($data['restock']);
                        $lines[] = ['item_id' => (int) $rl['item_id'], 'description' => 'Return ' . $r['number'] . ': ' . $rl['name'], 'qty' => (float) $rl['qty'],
                            'unit_price' => (float) $rl['unit_price'], 'tax_rate_id' => $rl['tax_rate_id'], 'unit_cost' => $restock ? $cost : 0];
                        Database::query('UPDATE shop_order_lines SET returned_qty = returned_qty + ? WHERE id = ?', [$rl['qty'], $rl['order_line_id']]);
                    }
                    $cn = DocumentService::creditNote(self::contactFor($o), $lines, $o['number'] . ' / ' . $r['number'], $o['ship_region'], 'shop_return', $id);
                    Database::update('shop_returns', ['status' => 'received', 'credit_note_id' => $cn, 'staff_note' => $note ?? $r['staff_note']], 'id = ?', [$id]);
                    self::event((int) $o['id'], 'return_received', 'Returned goods received' . (!empty($data['restock']) ? ' and restocked' : ' (not restocked - damaged)'), 'staff', $uid);
                    break;
                case 'refund':
                    if ($r['status'] !== 'received') {
                        throw new ValidationException([], 'Receive the goods before refunding.');
                    }
                    $cn = Database::one('SELECT total, amount_paid FROM documents WHERE id = ?', [$r['credit_note_id']]);
                    $amt = round((float) $cn['total'] - (float) $cn['amount_paid'], 2);
                    if ($amt > 0) {
                        DocumentService::recordPayment((int) $r['credit_note_id'], $amt, (int) ($data['account_id'] ?? 0), date('Y-m-d'), $data['refund_ref'] ?? null);
                    }
                    Database::update('shop_returns', ['status' => 'refunded', 'refund_amount' => $cn['total'], 'refund_method' => mb_substr((string) ($data['refund_method'] ?? 'bank'), 0, 30), 'refund_ref' => mb_substr((string) ($data['refund_ref'] ?? ''), 0, 60) ?: null], 'id = ?', [$id]);
                    $fully = !Database::value('SELECT COUNT(*) FROM shop_order_lines WHERE order_id = ? AND returned_qty < qty', [$o['id']]);
                    if ($fully) {
                        Database::update('shop_orders', ['payment_status' => 'refunded'], 'id = ?', [$o['id']]);
                    }
                    self::event((int) $o['id'], 'refunded', 'Refund of ' . money($cn['total']) . ' issued' . (!empty($data['refund_ref']) ? ' · ref ' . $data['refund_ref'] : ''), 'staff', $uid);
                    break;
                default:
                    throw new ValidationException([], 'Unknown action.');
            }
            Audit::log('shop_return', $id, $action);
        });
    }

    // ------------------------------------------------------------------ reviews

    /** Items this customer received and has not reviewed yet */
    public static function reviewable(int $customerId, int $orderId): array
    {
        return array_map('intval', array_column(Database::all(
            "SELECT DISTINCT l.item_id FROM shop_order_lines l JOIN shop_orders o ON o.id = l.order_id
              WHERE o.id = ? AND o.customer_id = ? AND o.status = 'delivered'
                AND NOT EXISTS (SELECT 1 FROM shop_reviews r WHERE r.customer_id = o.customer_id AND r.item_id = l.item_id)",
            [$orderId, $customerId]
        ), 'item_id'));
    }
}
