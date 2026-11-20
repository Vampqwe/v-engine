#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Запуск возможен только из CLI.\n");
    exit(1);
}

$cacheDir = dirname(__DIR__) . '/storage/cache';

$targets = [
    $cacheDir . '/sites',
    $cacheDir . '/routes.compiled.php',
    $cacheDir . '/di',
];

foreach (glob($cacheDir . '/routes-*.compiled.php') ?: [] as $routeCache) {
    $targets[] = $routeCache;
}

foreach ($targets as $target) {
    if (is_file($target)) {
        @unlink($target);
        echo "[OK] Удален файл: {$target}\n";
    } elseif (is_dir($target)) {
        removeDirectory($target);
        echo "[OK] Очищена директория: {$target}\n";
    }
}

echo "Системный кэш ядра успешно сброшен.\n";

function removeDirectory(string $dir): void
{
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? removeDirectory($path) : @unlink($path);
    }
    @rmdir($dir);
}
