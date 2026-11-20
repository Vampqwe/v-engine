<?php

declare(strict_types=1);

$rawProxies = (string) ($_ENV['TRUSTED_PROXIES'] ?? $_SERVER['TRUSTED_PROXIES'] ?? '127.0.0.1,::1');
$trustedProxies = array_values(array_filter(array_map('trim', explode(',', $rawProxies))));
$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod';
if (!in_array($appEnv, ['dev', 'prod'], true)) {
    throw new \RuntimeException('APP_ENV должен быть dev или prod');
}

$debugValue = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? ($appEnv === 'dev');
$debug = filter_var($debugValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($debug === null) {
    throw new \RuntimeException('APP_DEBUG должен быть boolean-значением');
}

foreach ($trustedProxies as $proxy) {
    if ($proxy === '*') {
        throw new \RuntimeException('TRUSTED_PROXIES не может содержать wildcard *');
    }

    $parts = explode('/', $proxy, 2);
    if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
        throw new \RuntimeException("Некорректный доверенный прокси: {$proxy}");
    }

    if (isset($parts[1])) {
        $maxBits = str_contains($parts[0], ':') ? 128 : 32;
        if (!ctype_digit($parts[1]) || (int) $parts[1] > $maxBits) {
            throw new \RuntimeException("Некорректная CIDR-сеть доверенного прокси: {$proxy}");
        }
    }
}

return [
    'name'            => $_ENV['APP_NAME'] ?? $_SERVER['APP_NAME'] ?? 'V-engine',
    'env'             => $appEnv,
    'debug'           => $debug,
    'trusted_proxies' => $trustedProxies,
];
