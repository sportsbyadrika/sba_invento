<?php
/**
 * Copy this file to config/config.php and adjust the values.
 * config/config.php is git-ignored - never commit real credentials.
 */
return [
    'app' => [
        'name'        => 'SBA Invento',
        'env'         => 'local',            // local | production
        'debug'       => true,               // MUST be false in production
        'url'         => 'http://localhost:8000', // used in e-mails (password reset links)
        'base_path'   => '',                 // e.g. '/invento' when installed in a sub-folder; '' = auto
        'timezone'    => 'UTC',              // storage timezone - keep UTC
        'force_https' => false,              // true in production
        // Web installer (/install) for hosting without a terminal: set any long random text,
        // open https://your-domain/install, then set it back to '' after installing.
        'install_key' => '',
        // 'auto' detects whether the .htaccess rewrite rules work; false forces /index.php/... URLs
        'pretty_urls' => 'auto',
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'sba_invento',
        'username' => 'sba',
        'password' => 'sba_pass',
        'charset'  => 'utf8mb4',
    ],
    'security' => [
        'max_login_attempts'     => 5,       // lock the user after N consecutive failures
        'ip_max_failures'        => 20,      // block an IP after N failures ...
        'ip_window_minutes'      => 15,      // ... within this window
        'session_idle_minutes'   => 30,
        'session_name'           => 'sba_sid',
        'reset_token_minutes'    => 60,
        'password_min_length'    => 8,
        'trusted_proxies'        => [],      // e.g. ['10.0.0.1'] when behind a load balancer / Cloudflare
    ],
    'geoip' => [
        'enabled'  => true,
        'endpoint' => 'https://ipwho.is/{ip}', // free HTTPS lookup; swap for MaxMind GeoLite2 in production
        'timeout'  => 2,
        'cache_days' => 30,
    ],
    'mail' => [
        'driver'     => 'log',               // log | smtp | mail
        'from_email' => 'no-reply@example.com',
        'from_name'  => 'SBA Invento',
        'smtp' => [
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'encryption' => 'tls',           // tls | ssl | none
            'username'   => '',
            'password'   => '',
            'timeout'    => 10,
        ],
    ],
    'list' => [
        'default_per_page' => 25,
        'per_page_options' => [10, 25, 50, 100, 250],
    ],
];
