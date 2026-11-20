<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use VEngine\Kernel\Application;
use VEngine\System\Error\ErrorRenderer;
use VEngine\System\Http\HttpException;

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
        $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
        http_response_code($statusCode);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isApi = str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/');
        if ($isApi || str_contains($accept, 'application/json')) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'error'   => true,
                'status'  => $statusCode,
                'message' => $statusCode === 404 ? 'Not Found' : 'Internal Server Error',
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
    }

    $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod';
    $debugValue = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? ($appEnv === 'dev');
    $isDebug = $appEnv === 'dev' && filter_var($debugValue, FILTER_VALIDATE_BOOL);
    $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
    echo (new ErrorRenderer())->render($e, $isDebug, $statusCode);
}
