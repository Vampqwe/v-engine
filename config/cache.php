<?php

declare(strict_types=1);

$driver = $_ENV['CACHE_DRIVER'] ?? $_SERVER['CACHE_DRIVER'] ?? 'file';
$fallback = $_ENV['CACHE_REDIS_FALLBACK'] ?? $_SERVER['CACHE_REDIS_FALLBACK'] ?? 'true';

if (!in_array($driver, ['file', 'redis'], true)) {
    throw new \RuntimeException('CACHE_DRIVER должен быть file или redis');
}

$fallback = filter_var($fallback, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
if ($fallback === null) {
    throw new \RuntimeException('CACHE_REDIS_FALLBACK должен быть boolean-значением');
}

return [
    'driver'          => $driver,
    'redis_failover'  => $driver === 'redis' && $fallback,
];
