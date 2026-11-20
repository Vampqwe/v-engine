<?php

declare(strict_types=1);

$rawProxies = (string) ($_ENV['TRUSTED_PROXIES'] ?? $_SERVER['TRUSTED_PROXIES'] ?? '127.0.0.1,::1');
$trustedProxies = array_values(array_filter(array_map('trim', explode(',', $rawProxies))));

return [
    'name'            => $_ENV['APP_NAME'] ?? $_SERVER['APP_NAME'] ?? 'V-engine',
    'env'             => $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev',
    'trusted_proxies' => $trustedProxies,
];
