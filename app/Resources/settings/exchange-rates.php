<?php
use App\Core\Auth;
use App\Core\Database;

return [
    'title' => 'Exchange Rate History',
    'singular' => 'Exchange Rate',
    'icon' => 'refresh-cw',
    'admin_only' => true,
    'description' => 'Dated rates used to convert foreign currency transactions and for revaluation.',
    'table' => 'exchange_rates',
    'creator' => true,
    'drawer_width' => 'max-w-lg',
    'columns' => [
        'effective_date' => ['label' => 'Date', 'sql' => 't.effective_date', 'format' => 'date'],
        'currency_code' => ['label' => 'Currency', 'sql' => 't.currency_code', 'format' => 'code'],
        'rate' => ['label' => 'Rate to Base', 'sql' => 't.rate', 'align' => 'right', 'render' => static fn ($r) => '<span class="tabular-nums">' . e(rtrim(rtrim(number_format((float) $r['rate'], 8), '0'), '.')) . '</span>'],
        'source' => ['label' => 'Source', 'sql' => 't.source'],
    ],
    'default_sort' => ['effective_date', 'desc'],
    'search' => ['t.currency_code'],
    'filters' => [
        'currency_code' => ['label' => 'Currency', 'type' => 'select', 'sql' => 't.currency_code', 'options' => static fn () => array_column(Database::all('SELECT code FROM currencies WHERE company_id = ? AND is_base = 0 ORDER BY code', [Auth::instance()->companyId()]), 'code', 'code')],
        'date' => ['label' => 'Date', 'type' => 'daterange', 'sql' => 't.effective_date'],
    ],
    'stats' => [['label' => 'Rates', 'sql' => 'COUNT(*)', 'icon' => 'refresh-cw'], ['label' => 'Currencies', 'sql' => 'COUNT(DISTINCT t.currency_code)', 'icon' => 'coins', 'tone' => 'indigo']],
    'fields' => [
        'currency_code' => ['label' => 'Currency', 'type' => 'lookup', 'required' => true, 'options' => static fn () => array_column(Database::all('SELECT code, CONCAT(code, " - ", name) label FROM currencies WHERE company_id = ? AND is_base = 0 ORDER BY code', [Auth::instance()->companyId()]), 'label', 'code')],
        'effective_date' => ['label' => 'Effective Date', 'type' => 'date', 'required' => true, 'default' => date('Y-m-d')],
        'rate' => ['label' => 'Rate (1 unit = ? base)', 'type' => 'decimal', 'required' => true, 'min' => 0],
    ],
    'hooks' => [
        'before_save' => static function (array $d, ?array $e): array {
            $dup = Database::value('SELECT id FROM exchange_rates WHERE company_id = ? AND currency_code = ? AND effective_date = ?' . ($e ? ' AND id <> ' . (int) $e['id'] : ''), [Auth::instance()->companyId(), $d['currency_code'], $d['effective_date']]);
            if ($dup) {
                throw new App\Core\ValidationException(['effective_date' => 'A rate for this currency and date already exists.']);
            }
            return $d;
        },
        'after_save' => static function (int $id, array $d): void {
            // keep the currency master's current rate in sync with the latest dated rate
            $latest = Database::one('SELECT rate FROM exchange_rates WHERE company_id = ? AND currency_code = ? ORDER BY effective_date DESC LIMIT 1', [Auth::instance()->companyId(), $d['currency_code']]);
            if ($latest) {
                Database::query('UPDATE currencies SET exchange_rate = ? WHERE company_id = ? AND code = ? AND is_base = 0', [$latest['rate'], Auth::instance()->companyId(), $d['currency_code']]);
            }
        },
    ],
];
