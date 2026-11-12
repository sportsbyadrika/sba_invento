<?php
use App\Core\Auth;
use App\Core\Database;

$status = ['pending' => ['Pending', 'amber'], 'approved' => ['Published', 'green'], 'hidden' => ['Hidden', 'slate']];
$set = static fn (string $st) => static function ($row) use ($st) {
    Database::update('shop_reviews', ['status' => $st], 'id = ? AND company_id = ?', [$row['id'], Auth::instance()->companyId()]);
    return $st === 'approved' ? 'Review published.' : 'Review hidden.';
};

return [
    'title' => 'Customer Reviews',
    'singular' => 'Review',
    'icon' => 'star',
    'description' => 'Reviews from verified buyers. Publish them to show on the product page.',
    'table' => 'shop_reviews',
    'from' => 'shop_reviews t JOIN items i ON i.id = t.item_id JOIN shop_customers c ON c.id = t.customer_id',
    'module' => 'shop',
    'readonly' => true, 'can_create' => false, 'can_delete' => true,
    'columns' => [
        'created_at' => ['label' => 'Date', 'sql' => 't.created_at', 'format' => 'datetime'],
        'item' => ['label' => 'Item', 'sql' => 'i.name'],
        'customer' => ['label' => 'Customer', 'sql' => 'c.name'],
        'rating' => ['label' => 'Rating', 'sql' => 't.rating', 'render' => static fn ($r) => '<span class="text-amber-500">' . str_repeat('★', (int) $r['rating']) . '</span><span class="text-slate-300">' . str_repeat('★', 5 - (int) $r['rating']) . '</span>'],
        'review' => ['label' => 'Review', 'sql' => "CONCAT_WS(' - ', t.title, t.body)", 'sortable' => false, 'render' => static fn ($r) => '<span class="line-clamp-2 max-w-md text-sm">' . e($r['review']) . '</span>'],
        'status' => ['label' => 'Status', 'sql' => 't.status', 'format' => 'badge', 'map' => $status],
    ],
    'default_sort' => ['created_at', 'desc'],
    'search' => ['i.name', 'c.name', 't.title', 't.body'],
    'filters' => [
        'status' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.status', 'options' => array_map(static fn ($s) => $s[0], $status)],
        'rating' => ['label' => 'Rating', 'type' => 'select', 'sql' => 't.rating', 'options' => ['5' => '5 stars', '4' => '4 stars', '3' => '3 stars', '2' => '2 stars', '1' => '1 star']],
    ],
    'stats' => [
        ['label' => 'Reviews', 'sql' => 'COUNT(*)', 'icon' => 'star'],
        ['label' => 'Pending', 'sql' => "COALESCE(SUM(t.status = 'pending'),0)", 'icon' => 'bell', 'tone' => 'amber'],
        ['label' => 'Average rating', 'sql' => "COALESCE(ROUND(AVG(t.rating), 1), 0)", 'format' => 'text', 'icon' => 'star', 'tone' => 'emerald'],
    ],
    'fields' => [],
    'row_actions' => [
        'publish' => ['label' => 'Publish', 'icon' => 'check', 'tone' => 'emerald', 'visible' => static fn ($r) => $r['status'] !== 'approved', 'handler' => $set('approved')],
        'hide' => ['label' => 'Hide', 'icon' => 'circle-slash', 'visible' => static fn ($r) => $r['status'] !== 'hidden', 'handler' => $set('hidden')],
    ],
];
