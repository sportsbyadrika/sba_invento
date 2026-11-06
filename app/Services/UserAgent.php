<?php
declare(strict_types=1);

namespace App\Services;

/** Small dependency-free User-Agent parser (browser, version, OS, device type). */
final class UserAgent
{
    public static function parse(string $ua): array
    {
        $browser = 'Unknown';
        $version = '';
        $map = [
            'Edge'    => '/Edg(?:e|A|iOS)?\/([\d.]+)/',
            'Opera'   => '/(?:OPR|Opera)\/([\d.]+)/',
            'Samsung' => '/SamsungBrowser\/([\d.]+)/',
            'Chrome'  => '/(?:Chrome|CriOS)\/([\d.]+)/',
            'Firefox' => '/(?:Firefox|FxiOS)\/([\d.]+)/',
            'Safari'  => '/Version\/([\d.]+).*Safari/',
            'IE'      => '/(?:MSIE |Trident\/.*rv:)([\d.]+)/',
        ];
        foreach ($map as $name => $re) {
            if (preg_match($re, $ua, $m)) {
                $browser = $name;
                $version = $m[1];
                break;
            }
        }
        $os = 'Unknown';
        $osMap = [
            'Windows' => '/Windows NT ([\d.]+)/',
            'iOS'     => '/(?:iPhone|iPad|iPod).*OS ([\d_]+)/',
            'Android' => '/Android ([\d.]+)/',
            'macOS'   => '/Mac OS X ([\d_.]+)/',
            'ChromeOS'=> '/CrOS/',
            'Linux'   => '/Linux/',
        ];
        foreach ($osMap as $name => $re) {
            if (preg_match($re, $ua, $m)) {
                $os = $name;
                if (isset($m[1])) {
                    $v = str_replace('_', '.', $m[1]);
                    if ($name === 'Windows') {
                        $v = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'][$v] ?? $v;
                    }
                    $os .= ' ' . $v;
                }
                break;
            }
        }
        $device = 'Desktop';
        if (preg_match('/iPad|Tablet/i', $ua) || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile'))) {
            $device = 'Tablet';
        } elseif (preg_match('/Mobile|iPhone|iPod/i', $ua)) {
            $device = 'Mobile';
        } elseif (preg_match('/bot|crawl|spider|curl|wget|python/i', $ua)) {
            $device = 'Bot';
        }
        return ['browser' => $browser, 'browser_version' => substr($version, 0, 30), 'os' => substr($os, 0, 60), 'device_type' => $device];
    }
}
