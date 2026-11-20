<?php

declare(strict_types=1);

return [
    'host'     => $_ENV['REDIS_HOST'] ?? $_SERVER['REDIS_HOST'] ?? '127.0.0.1',
    'port'     => (int) ($_ENV['REDIS_PORT'] ?? $_SERVER['REDIS_PORT'] ?? 6379),
    'password' => $_ENV['REDIS_PASSWORD'] ?? $_SERVER['REDIS_PASSWORD'] ?? '',
    'database' => (int) ($_ENV['REDIS_DB'] ?? $_SERVER['REDIS_DB'] ?? 0),
    'prefix'   => $_ENV['REDIS_PREFIX'] ?? $_SERVER['REDIS_PREFIX'] ?? 'vengine:',
    'timeout'  => (float) ($_ENV['REDIS_TIMEOUT'] ?? $_SERVER['REDIS_TIMEOUT'] ?? 2.0),
];
