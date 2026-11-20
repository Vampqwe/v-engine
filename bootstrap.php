<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use VEngine\Kernel\Application;
use VEngine\System\Error\ErrorRenderer;

ob_start();
define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/src/System/Error/ErrorRendererInterface.php';
require_once BASE_PATH . '/src/System/Error/ErrorRenderer.php';

try {
    require BASE_PATH . '/vendor/autoload.php';

    // Загружаем .env до старта ядра, чтобы знать APP_ENV при любых сбоях инициализации
    if (class_exists(Dotenv::class)) {
        Dotenv::createImmutable(BASE_PATH)->safeLoad();
    }

    $app = new Application(BASE_PATH);
    $app->run();
} catch (\Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Резервная запись в лог, если основной Logger еще не успел запуститься
    $logLine = sprintf(
        "[%s] [CRITICAL] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
    );
    @file_put_contents(BASE_PATH . '/log/Bootstrap.log', $logLine, FILE_APPEND | LOCK_EX);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $isDebug = (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev') === 'dev');
    echo (new ErrorRenderer())->render($e, $isDebug);
}
