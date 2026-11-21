#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Запуск возможен только из CLI.\n");
    exit(1);
}

array_splice($argv, 1, 0, ['cache:clear']);
require __DIR__ . '/console.php';
