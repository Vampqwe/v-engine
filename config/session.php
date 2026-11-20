<?php

declare(strict_types=1);

$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod';
if (!in_array($appEnv, ['dev', 'prod'], true)) {
    throw new \RuntimeException('APP_ENV должен быть dev или prod');
}
$secureCookie = $_ENV['SESSION_SECURE'] ?? $_SERVER['SESSION_SECURE'] ?? ($appEnv === 'prod');
$driver = $_ENV['SESSION_DRIVER'] ?? $_SERVER['SESSION_DRIVER'] ?? 'file';
$secureCookie = filter_var($secureCookie, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

if ($secureCookie === null) {
    throw new \RuntimeException('SESSION_SECURE должен быть boolean-значением');
}

if (!in_array($driver, ['file', 'redis'], true)) {
    throw new \RuntimeException('SESSION_DRIVER должен быть file или redis');
}

return [
    'driver'   => $driver,
    'name'     => 'VENGINE_SESSID',
    'lifetime' => 7200, // Абсолютный срок жизни cookie: 2 часа с момента выдачи.
    'path'     => '/',
    'secure'   => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
];
