<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class LoginLogger
{
    /**
     * @param array $client values posted by the login form: tz, screen, platform, lang
     */
    public static function log(string $event, ?int $userId, string $username, bool $success, ?string $reason, array $client): void
    {
        try {
            $ip = client_ip();
            $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $parsed = UserAgent::parse($ua);
            $geo = GeoIp::lookup($ip);
            $tz = (string) ($client['tz'] ?? '');
            if ($tz !== '' && !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
                $tz = '';
            }
            Database::insert('login_logs', [
                'user_id'            => $userId,
                'username_attempted' => mb_substr($username, 0, 100),
                'event'              => $event,
                'success'            => $success ? 1 : 0,
                'reason'             => $reason ? mb_substr($reason, 0, 120) : null,
                'ip_address'         => $ip,
                'country'            => $geo['country'],
                'region'             => $geo['region'],
                'city'               => $geo['city'],
                'isp'                => $geo['isp'],
                'browser'            => $parsed['browser'],
                'browser_version'    => $parsed['browser_version'],
                'os'                 => $parsed['os'],
                'device_type'        => $parsed['device_type'],
                'platform'           => self::clean($client['platform'] ?? null, 60),
                'screen'             => self::clean($client['screen'] ?? null, 20),
                'language'           => self::clean($client['lang'] ?? null, 20),
                'client_timezone'    => $tz ?: null,
                'user_agent'         => $ua,
                'session_hash'       => session_id() ? hash('sha256', session_id()) : null,
                'created_at'         => now_utc(),
            ]);
        } catch (\Throwable $e) {
            error_log('Login log failed: ' . $e->getMessage());
        }
    }

    private static function clean(mixed $v, int $len): ?string
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        return mb_substr(preg_replace('/[^\w\s.\-x×\/()]/u', '', $v) ?? '', 0, $len) ?: null;
    }
}
