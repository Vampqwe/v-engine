<?php

declare(strict_types=1);

return [
    'host'      => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1',
    'port'      => (int) ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306),
    'name'      => $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'v_engine',
    'user'      => $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root',
    'password'  => $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
