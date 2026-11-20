<?php

declare(strict_types=1);

$driver = $_ENV['RATE_LIMIT_DRIVER'] ?? $_SERVER['RATE_LIMIT_DRIVER'] ?? 'file';
if (!in_array($driver, ['file', 'redis'], true)) {
    throw new \RuntimeException('RATE_LIMIT_DRIVER должен быть file или redis');
}

return [
    'driver' => $driver,
    'fallback_hold_seconds' => 300,
    'global' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
        'fail_open'     => true,
    ],
    'admin_login' => [
        'max_attempts' => 5,
        'decay_seconds' => 300,
        'fail_open'     => false,
    ],
    'home_submit' => [
        'max_attempts' => 10,
        'decay_seconds' => 60,
        'fail_open'     => false,
    ],
];
