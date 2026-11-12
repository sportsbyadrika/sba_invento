<?php
/** Online orders list. $dispatch = only open orders, oldest first (work queue). */
use App\Services\Shop;

return static function (bool $dispatch): array {
    $stages = Shop::STAGES;
    $cfg = [
        'title' => $dispatch ? 'Dispatch' : 'Online Orders',
        'singular' => 'Order',
        'icon' => $dispatch ? 'truck' : 'shopping-cart',
        'description' => $dispatch
            ? 'Open web orders, oldest first. Open an order to confirm, pack, ship (invoice + stock out) and deliver (COD cash in).'
            : 'Every order placed in the online shop with its delivery stage, payment and invoice.',
        'table' => 'shop_orders',
        'from' => 'shop_orders t JOIN shop_customers c ON c.id = t.customer_id',
        'module' => 'shop',
        'readonly' => true, 'can_create' => false, 'can_delete' => false,
        'row_url' => '/shop/orders/{id}',
        'columns' => [
            'placed_at' => ['label' => 'Placed', 'sql' => 't.placed_at', 'format' => 'datetime'],
            'number' => ['label' => 'Order #', 'sql' => 't.number', 'format' => 'code'],
            'customer' => ['label' => 'Customer', 'sql' => 'c.name', 'render' => static fn ($r) => '<span class="font-medium text-slate-900">' . e($r['customer']) . '</span><span class="block text-xs text-slate-500">' . e($r['phone']) . '</span>'],
            'ship_city' => ['label' => 'City', 'sql' => 't.ship_city'],
            'items' => ['label' => 'Items', 'sql' => '(SELECT SUM(l.qty) FROM shop_order_lines l WHERE l.order_id = t.id)', 'format' => 'number', 'align' => 'right'],
            'total' => ['label' => 'Total', 'sql' => 't.total', 'format' => 'money', 'align' => 'right'],
            'payment' => ['label' => 'Payment', 'sql' => "CONCAT(t.payment_method, '/', t.payment_status)", 'render' => static fn ($r) => ($r['payment_method'] === 'cod' ? '<span class="badge badge-slate">COD</span> ' : '<span class="badge badge-blue">Online</span> ') . ($r['payment_status'] === 'paid' ? '<span class="badge badge-green">Paid</span>' : ($r['payment_status'] === 'refunded' ? '<span class="badge badge-violet">Refunded</span>' : '<span class="badge badge-amber">Due</span>'))],
            'status' => ['label' => 'Stage', 'sql' => 't.status', 'format' => 'badge', 'map' => $stages],
            'age' => ['label' => 'Waiting', 'sql' => 'TIMESTAMPDIFF(HOUR, t.placed_at, UTC_TIMESTAMP())', 'align' => 'right', 'render' => static function ($r) {
                if (in_array($r['status'], ['delivered', 'cancelled'], true)) { return '<span class="text-slate-300">—</span>'; }
                $h = (int) $r['age'];
                return '<span class="tabular-nums ' . ($h > 48 ? 'font-semibold text-rose-600' : ($h > 24 ? 'text-amber-600' : 'text-slate-600')) . '">' . ($h >= 24 ? intdiv($h, 24) . 'd ' . ($h % 24) . 'h' : $h . 'h') . '</span>';
            }],
        ],
        'select_extra' => ['phone' => "CONCAT(c.phone_code, ' ', c.phone)", 'payment_method' => 't.payment_method', 'payment_status' => 't.payment_status'],
        'default_sort' => $dispatch ? ['placed_at', 'asc'] : ['placed_at', 'desc'],
        'search' => ['t.number', 'c.name', 'c.email', 'c.phone', 't.ship_city', 't.tracking_no'],
        'filters' => [
            'status' => ['label' => 'Stage', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], $dispatch ? array_intersect_key($stages, array_flip(['placed', 'confirmed', 'packed', 'shipped', 'out_for_delivery'])) : $stages)],
            'payment_status' => ['label' => 'Payment', 'type' => 'select', 'sql' => 't.payment_status', 'options' => ['pending' => 'Due', 'paid' => 'Paid', 'refunded' => 'Refunded']],
            'date' => ['label' => 'Placed on', 'type' => 'daterange', 'sql' => 't.placed_at'],
            'city' => ['label' => 'City', 'type' => 'text', 'sql' => 't.ship_city'],
            'total' => ['label' => 'Order total', 'type' => 'numrange', 'sql' => 't.total'],
        ],
        'stats' => $dispatch ? [
            ['label' => 'To confirm', 'sql' => "COALESCE(SUM(t.status = 'placed'),0)", 'icon' => 'bell', 'tone' => 'amber'],
            ['label' => 'To pack', 'sql' => "COALESCE(SUM(t.status = 'confirmed'),0)", 'icon' => 'package', 'tone' => 'cyan'],
            ['label' => 'To ship', 'sql' => "COALESCE(SUM(t.status = 'packed'),0)", 'icon' => 'truck', 'tone' => 'indigo'],
            ['label' => 'On the way', 'sql' => "COALESCE(SUM(t.status IN ('shipped','out_for_delivery')),0)", 'icon' => 'map-pin', 'tone' => 'violet'],
        ] : [
            ['label' => 'Orders', 'sql' => 'COUNT(*)', 'icon' => 'shopping-cart'],
            ['label' => 'Sales value', 'sql' => "COALESCE(SUM(CASE WHEN t.status <> 'cancelled' THEN t.total END),0)", 'format' => 'money', 'icon' => 'trending-up', 'tone' => 'emerald'],
            ['label' => 'Open (not delivered)', 'sql' => "COALESCE(SUM(t.status NOT IN ('delivered','cancelled')),0)", 'icon' => 'truck', 'tone' => 'amber'],
            ['label' => 'COD to collect', 'sql' => "COALESCE(SUM(CASE WHEN t.payment_method = 'cod' AND t.payment_status = 'pending' AND t.status <> 'cancelled' THEN t.total END),0)", 'format' => 'money', 'icon' => 'banknote', 'tone' => 'violet'],
        ],
        'fields' => [],
    ];
    if ($dispatch) {
        $cfg['where'] = static fn () => ["t.status IN ('placed','confirmed','packed','shipped','out_for_delivery')", []];
    }
    return $cfg;
};
