#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use VEngine\Kernel\Application;
use VEngine\System\Console\ConsoleApplicationInterface;
use VEngine\System\Console\StreamOutput;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Запуск возможен только из CLI.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

try {
    $container = (new Application($basePath))->getContainer();
    $console = $container->get(ConsoleApplicationInterface::class);
    exit($console->run($argv, new StreamOutput()));
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
