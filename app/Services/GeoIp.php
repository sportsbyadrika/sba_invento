<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * IP -> location lookup with a DB cache, so each IP is looked up at most once
 * every `cache_days`.  Private / reserved ranges are never sent out.
 */
final class GeoIp
{
    public static function lookup(string $ip): array
    {
        $empty = ['country' => null, 'region' => null, 'city' => null, 'isp' => null];
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country' => 'Local network', 'region' => null, 'city' => null, 'isp' => null];
        }
        if (!config('geoip.enabled', false)) {
            return $empty;
        }
        $cached = Database::one('SELECT country, region, city, isp, fetched_at FROM ip_locations WHERE ip_address = ?', [$ip]);
        $maxAge = (int) config('geoip.cache_days', 30) * 86400;
        if ($cached && strtotime($cached['fetched_at'] . ' UTC') > time() - $maxAge) {
            unset($cached['fetched_at']);
            return $cached;
        }
        $result = $empty;
        try {
            $endpoint = str_replace('{ip}', rawurlencode($ip), (string) config('geoip.endpoint'));
            $ctx = stream_context_create(['http' => ['timeout' => (int) config('geoip.timeout', 2), 'ignore_errors' => true, 'header' => "User-Agent: SBA-Invento\r\n"]]);
            $raw = @file_get_contents($endpoint, false, $ctx);
            $json = $raw ? json_decode($raw, true) : null;
            if (is_array($json) && ($json['success'] ?? true) !== false) {
                $result = [
                    'country' => self::cut($json['country'] ?? $json['country_name'] ?? null),
                    'region'  => self::cut($json['region'] ?? $json['regionName'] ?? null),
                    'city'    => self::cut($json['city'] ?? null),
                    'isp'     => self::cut($json['connection']['isp'] ?? $json['isp'] ?? $json['org'] ?? null, 150),
                ];
                Database::query(
                    'REPLACE INTO ip_locations (ip_address, country, region, city, isp, fetched_at) VALUES (?,?,?,?,?,?)',
                    [$ip, $result['country'], $result['region'], $result['city'], $result['isp'], now_utc()]
                );
            }
        } catch (\Throwable) {
            // Location is best-effort - never block a login because the lookup failed.
        }
        if ($result === $empty) {
            // remember the failure for a day so an unreachable lookup service never slows every login
            Database::query(
                'REPLACE INTO ip_locations (ip_address, country, region, city, isp, fetched_at) VALUES (?,NULL,NULL,NULL,NULL,?)',
                [$ip, gmdate('Y-m-d H:i:s', time() - $maxAge + 86400)]
            );
        }
        return $result;
    }

    private static function cut(?string $v, int $len = 80): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $len);
    }
}
