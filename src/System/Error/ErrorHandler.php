<?php

declare(strict_types=1);

namespace VEngine\System\Error;

use ErrorException as PhpErrorException;
use Throwable;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Http\HttpException;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Session\SessionInterface;
use VEngine\System\Validator\ValidationException;

final class ErrorHandler implements ErrorHandlerInterface
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        private readonly ErrorRendererInterface $renderer,
        private readonly ConfigInterface $config,
        private readonly RequestInterface $request,
        private readonly SessionInterface $session
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
        if ($e instanceof ValidationException) {
            $this->handleValidationException($e);
            return;
        }

        try {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
        } catch (Throwable) {
            // Игнорируем сбой логирования, чтобы не вызвать фатальную ошибку
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $statusCode = ($e instanceof HttpException)
            ? $e->getStatusCode()
            : 500;

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $isDebug = ($this->config->env('APP_ENV', 'prod') === 'dev');
        echo $this->renderer->render($e, $isDebug);
    }

    private function handleValidationException(ValidationException $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $accept = (string) $this->request->header('accept', '');
        $isApiOrAjax = $this->request->isAjax()
            || str_contains($accept, 'application/json')
            || str_starts_with($this->request->uri(), '/api/');

        if ($isApiOrAjax) {
            Response::json([
                'error'   => true,
                'message' => $e->getMessage(),
                'errors'  => $e->getErrors(),
            ], 422)->send();
            return;
        }

        $errors = $e->getErrors();

        // Сохраняем массив всех ошибок по полям и первую ошибку для общих уведомлений
        $this->session->flash('_errors', $errors);

        $firstField = array_key_first($errors);
        if ($firstField !== null && !empty($errors[$firstField][0])) {
            $this->session->flash('error', $errors[$firstField][0]);
        }

        // Сохраняем старый ввод формы (исключая чувствительные поля)
        $oldInput = $this->request->all();
        unset(
            $oldInput['password'],
            $oldInput['password_confirmation'],
            $oldInput['current_password'],
            $oldInput['_csrf_token']
        );
        $this->session->flash('_old_input', $oldInput);

        // Безопасный редирект назад (только на тот же хост)
        $redirectUrl = $this->resolveBackUrl();
        Response::redirect($redirectUrl, 302)->send();
    }

    private function resolveBackUrl(): string
    {
        $referer = $this->request->header('referer');
        if (is_string($referer) && $referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            // Отсекаем порт из HTTP_HOST при сравнении
            $currentHostOnly = explode(':', $currentHost)[0];

            if ($refererHost === null || $refererHost === $currentHostOnly) {
                $path = parse_url($referer, PHP_URL_PATH) ?: '/';
                $query = parse_url($referer, PHP_URL_QUERY);
                return $query !== null && $query !== '' ? "{$path}?{$query}" : $path;
            }
        }

        return $this->request->uri() !== '' ? $this->request->uri() : '/';
    }
}
