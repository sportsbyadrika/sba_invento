<?php
use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;

return [
    'title' => 'Currencies',
    'singular' => 'Currency',
    'icon' => 'coins',
    'admin_only' => true,
    'description' => 'Currencies the company transacts in. Rate = value of 1 unit in the base currency.',
    'table' => 'currencies',
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'code' => ['label' => 'Code', 'sql' => 't.code', 'format' => 'code'],
        'name' => ['label' => 'Name', 'sql' => 't.name', 'render' => static fn ($r) => e($r['name']) . ($r['is_base'] ? ' <span class="badge badge-cyan ml-1">Base</span>' : '')],
        'symbol' => ['label' => 'Symbol', 'sql' => 't.symbol'],
        'decimal_places' => ['label' => 'Decimals', 'sql' => 't.decimal_places', 'align' => 'right'],
        'exchange_rate' => ['label' => 'Rate to Base', 'sql' => 't.exchange_rate', 'align' => 'right', 'render' => static fn ($r) => '<span class="tabular-nums">' . e(rtrim(rtrim(number_format((float) $r['exchange_rate'], 6), '0'), '.')) . '</span>'],
        'updated_at' => ['label' => 'Rate Updated', 'sql' => 'COALESCE(t.updated_at, t.created_at)', 'format' => 'datetime'],
        'is_active' => ['label' => 'Status', 'sql' => 't.is_active', 'format' => 'status'],
    ],
    'select_extra' => ['is_base' => 't.is_base'],
    'default_sort' => ['code', 'asc'],
    'search' => ['t.code', 't.name'],
    'filters' => ['is_active' => ['label' => 'Status', 'type' => 'select', 'sql' => 't.is_active', 'options' => ['1' => 'Active', '0' => 'Inactive']]],
    'stats' => [
        ['label' => 'Currencies', 'sql' => 'COUNT(*)', 'icon' => 'coins'],
        ['label' => 'Active', 'sql' => 'COALESCE(SUM(t.is_active),0)', 'icon' => 'circle-check', 'tone' => 'emerald'],
        ['label' => 'Rates older than 7 days', 'sql' => 'COALESCE(SUM(t.is_base = 0 AND t.is_active = 1 AND COALESCE(t.updated_at, t.created_at) < UTC_TIMESTAMP() - INTERVAL 7 DAY),0)', 'icon' => 'clock', 'tone' => 'amber'],
    ],
    'fields' => [
        'code' => ['label' => 'ISO Code', 'required' => true, 'max' => 3, 'unique' => true, 'uppercase' => true, 'pattern' => '/^[A-Za-z]{3}$/', 'pattern_message' => 'Use the 3-letter ISO 4217 code.', 'create_only' => true],
        'name' => ['label' => 'Name', 'required' => true, 'max' => 60],
        'symbol' => ['label' => 'Symbol', 'required' => true, 'max' => 10],
        'decimal_places' => ['label' => 'Decimal Places', 'type' => 'select', 'options' => ['0' => '0', '2' => '2', '3' => '3 (KWD, OMR, BHD)'], 'default' => '2'],
        'exchange_rate' => ['label' => 'Exchange Rate (1 unit = ? base)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'help' => 'Saving a new rate also stores it in the exchange-rate history for today.'],
        'is_active' => ['label' => 'Active', 'type' => 'checkbox', 'default' => 1],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $e): array {
            if ($e && $e['is_base']) {
                $d['exchange_rate'] = 1;
                $d['is_active'] = 1;
            }
            if ((float) $d['exchange_rate'] <= 0) {
                throw new ValidationException(['exchange_rate' => 'Rate must be greater than zero.']);
            }
            return $d;
        },
        'after_save' => static function (int $id, array $d, ?array $e): void {
            $code = $e['code'] ?? $d['code'];
            if (!$e || (float) $e['exchange_rate'] !== (float) $d['exchange_rate']) {
                Database::query(
                    'REPLACE INTO exchange_rates (company_id, currency_code, rate, effective_date, source, created_by) VALUES (?,?,?,?,?,?)',
                    [Auth::instance()->companyId(), $code, $d['exchange_rate'], gmdate('Y-m-d'), 'manual', Auth::instance()->id()]
                );
            }
        },
        'before_delete' => static function ($r): ?string {
            if ($r['is_base']) {
                return 'The base currency cannot be deleted.';
            }
            return Database::value('SELECT COUNT(*) FROM journals WHERE company_id = ? AND currency_code = ?', [$r['company_id'], $r['code']]) ? 'Transactions use this currency. Mark it inactive instead.' : null;
        },
    ],
    'can_delete_row' => static fn ($r) => !$r['is_base'],
];
