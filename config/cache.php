<?php

declare(strict_types=1);

$driver = $_ENV['CACHE_DRIVER'] ?? $_SERVER['CACHE_DRIVER'] ?? 'file';

if (!in_array($driver, ['file', 'redis'], true)) {
    throw new \RuntimeException('CACHE_DRIVER должен быть file или redis');
}

return [
    'driver' => $driver,
];
