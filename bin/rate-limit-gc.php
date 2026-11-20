#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use VEngine\Kernel\Application;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\RateLimit\FileRateLimiter;
use VEngine\System\TimeDate\TimeDateInterface;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Запуск возможен только из командной строки (CLI).\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

try {
    $container = (new Application($basePath))->getContainer();
    $limiter = new FileRateLimiter(
        $container->get('path.cache') . '/rate-limit',
        $container->get(TimeDateInterface::class),
        $container->get(LoggerInterface::class),
    );

    $removed = $limiter->collectExpiredStates();
    fwrite(STDOUT, "Удалено устаревших состояний rate limiter: {$removed}\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Не удалось очистить состояния rate limiter: {$exception->getMessage()}\n");
    exit(1);
}
