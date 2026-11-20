<?php

declare(strict_types=1);

$connectTimeout = (int) ($_ENV['DB_CONNECT_TIMEOUT'] ?? $_SERVER['DB_CONNECT_TIMEOUT'] ?? 2);
if ($connectTimeout < 1 || $connectTimeout > 30) {
    throw new \RuntimeException('DB_CONNECT_TIMEOUT должен быть в диапазоне от 1 до 30 секунд');
}

return [
    'host'      => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1',
    'port'      => (int) ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306),
    'name'      => $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'v_engine',
    'user'      => $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root',
    'password'  => $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '',
    'connect_timeout' => $connectTimeout,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
