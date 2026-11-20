<?php

declare(strict_types=1);

$connectTimeout = (float) ($_ENV['REDIS_CONNECT_TIMEOUT'] ?? $_SERVER['REDIS_CONNECT_TIMEOUT'] ?? 1.0);
$readTimeout = (float) ($_ENV['REDIS_TIMEOUT'] ?? $_SERVER['REDIS_TIMEOUT'] ?? 0.5);
if (
    !is_finite($connectTimeout)
    || $connectTimeout <= 0
    || $connectTimeout > 30
    || !is_finite($readTimeout)
    || $readTimeout <= 0
    || $readTimeout > 30
) {
    throw new \RuntimeException('Таймауты Redis должны быть больше 0 и не превышать 30 секунд');
}

return [
    'host'     => $_ENV['REDIS_HOST'] ?? $_SERVER['REDIS_HOST'] ?? '127.0.0.1',
    'port'     => (int) ($_ENV['REDIS_PORT'] ?? $_SERVER['REDIS_PORT'] ?? 6379),
    'password' => $_ENV['REDIS_PASSWORD'] ?? $_SERVER['REDIS_PASSWORD'] ?? '',
    'database' => (int) ($_ENV['REDIS_DB'] ?? $_SERVER['REDIS_DB'] ?? 0),
    'prefix'   => $_ENV['REDIS_PREFIX'] ?? $_SERVER['REDIS_PREFIX'] ?? 'vengine:',
    'connect_timeout' => $connectTimeout,
    'timeout'         => $readTimeout,
];
