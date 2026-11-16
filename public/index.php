<?php

declare(strict_types=1);

use VEngine\Kernel\Application;
use VEngine\System\Error\ErrorRenderer;

ob_start();
define('BASE_PATH', dirname(__DIR__));

// Подключаем рендерер напрямую на случай сбоя автозагрузчика или DI
require_once BASE_PATH . '/src/System/Error/ErrorRendererInterface.php';
require_once BASE_PATH . '/src/System/Error/ErrorRenderer.php';

try {
    require BASE_PATH . '/vendor/autoload.php';

    $app = new Application(BASE_PATH);
    $app->run();
} catch (\Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo (new ErrorRenderer())->render($e, true);
}