<?php
declare(strict_types=1);

namespace App\Services;

/** Country tax helpers: UAE VAT (TRN, emirates) and India GST (GSTIN, state codes). */
final class TaxRules
{
    public const EMIRATES = [
        'AUH' => 'Abu Dhabi', 'DXB' => 'Dubai', 'SHJ' => 'Sharjah', 'AJM' => 'Ajman',
        'UAQ' => 'Umm Al Quwain', 'RAK' => 'Ras Al Khaimah', 'FUJ' => 'Fujairah',
    ];

    public const IN_STATES = [
        '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
        '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura',
        '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha',
        '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat', '26' => 'Dadra & Nagar Haveli and Daman & Diu',
        '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala',
        '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar Islands', '36' => 'Telangana',
        '37' => 'Andhra Pradesh', '38' => 'Ladakh', '97' => 'Other Territory', '96' => 'Other Country',
    ];

    public const TREATMENTS = [
        'UAE_VAT' => ['vat_registered' => 'VAT Registered', 'non_registered' => 'Non VAT Registered', 'gcc_registered' => 'GCC VAT Registered', 'gcc_non_registered' => 'GCC Non Registered', 'designated_zone' => 'Designated Zone', 'overseas' => 'Overseas / Non-GCC'],
        'IN_GST' => ['registered' => 'Registered Business', 'composition' => 'Composition Dealer', 'unregistered' => 'Unregistered Business', 'consumer' => 'Consumer', 'sez' => 'SEZ', 'overseas' => 'Overseas', 'deemed_export' => 'Deemed Export'],
        'NONE' => ['standard' => 'Standard'],
    ];

    public static function places(string $regime): array
    {
        return match ($regime) {
            'UAE_VAT' => self::EMIRATES,
            'IN_GST' => array_combine(array_keys(self::IN_STATES), array_map(static fn ($c, $n) => "$c - $n", array_keys(self::IN_STATES), self::IN_STATES)),
            default => [],
        };
    }

    /** @return string|null error message */
    public static function validateTaxNumber(string $regime, string $number, string $state = ''): ?string
    {
        $number = strtoupper(trim($number));
        if ($number === '') {
            return null;
        }
        if ($regime === 'UAE_VAT') {
            return preg_match('/^100\d{12}$/', $number) ? null : 'UAE TRN must be 15 digits and start with 100.';
        }
        if ($regime === 'IN_GST') {
            if (!preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $number)) {
                return 'GSTIN must be 15 characters, e.g. 27AAPFU0939F1ZV.';
            }
            if (!self::gstinChecksumOk($number)) {
                return 'GSTIN check digit is invalid.';
            }
            if ($state !== '' && substr($number, 0, 2) !== $state) {
                return 'GSTIN state code (' . substr($number, 0, 2) . ') does not match the selected state.';
            }
        }
        return null;
    }

    /** GSTIN check digit (mod 36 Luhn variant) */
    public static function gstinChecksumOk(string $gstin): bool
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $v = strpos($chars, $gstin[$i]);
            $p = $v * (($i % 2) + 1);
            $sum += intdiv($p, 36) + ($p % 36);
        }
        $check = (36 - ($sum % 36)) % 36;
        return $chars[$check] === $gstin[14];
    }
}
