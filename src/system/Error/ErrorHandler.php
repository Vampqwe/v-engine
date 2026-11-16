<?php

declare(strict_types=1);

namespace VEngine\System\Error;

use ErrorException as PhpErrorException;
use Throwable;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Logger\LoggerInterface;

final class ErrorHandler implements ErrorHandlerInterface
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        private readonly ErrorRendererInterface $renderer,
        private readonly ConfigInterface $config
    ) {
        $this->logger = $logger->forModule('Error');
    }

    public function register(): void
    {
        ini_set('display_errors', '0');

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new PhpErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            $this->handleException($e);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $exception = new PhpErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                );
                $this->handleException($exception);
            }
        });
    }

    private function handleException(Throwable $e): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $isDebug = ($this->config->env('APP_ENV', 'prod') === 'dev');
        echo $this->renderer->render($e, $isDebug);
    }
}
