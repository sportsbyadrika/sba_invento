<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Gap-safe document numbering (row lock inside the caller's transaction). */
final class NumberSeries
{
    public const DEFAULTS = [
        'journal'  => 'JV-',
        'invoice'  => 'INV-',
        'quote'    => 'QT-',
        'bill'     => 'BILL-',
        'receipt'  => 'RCT-',
        'payment'  => 'PAY-',
        'customer' => 'C',
        'vendor'   => 'V',
    ];

    public static function next(int $companyId, string $docType): string
    {
        $row = Database::one('SELECT * FROM number_series WHERE company_id = ? AND doc_type = ? FOR UPDATE', [$companyId, $docType]);
        if (!$row) {
            Database::insert('number_series', ['company_id' => $companyId, 'doc_type' => $docType, 'prefix' => self::DEFAULTS[$docType] ?? strtoupper($docType) . '-', 'next_number' => 1, 'padding' => 5]);
            $row = Database::one('SELECT * FROM number_series WHERE company_id = ? AND doc_type = ? FOR UPDATE', [$companyId, $docType]);
        }
        Database::update('number_series', ['next_number' => (int) $row['next_number'] + 1], 'id = ?', [$row['id']]);
        return $row['prefix'] . str_pad((string) $row['next_number'], (int) $row['padding'], '0', STR_PAD_LEFT);
    }

    /** Preview without consuming a number */
    public static function peek(int $companyId, string $docType): string
    {
        $row = Database::one('SELECT * FROM number_series WHERE company_id = ? AND doc_type = ?', [$companyId, $docType]);
        $prefix = $row['prefix'] ?? (self::DEFAULTS[$docType] ?? strtoupper($docType) . '-');
        return $prefix . str_pad((string) ($row['next_number'] ?? 1), (int) ($row['padding'] ?? 5), '0', STR_PAD_LEFT);
    }
}
