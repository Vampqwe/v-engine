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
    private bool $responseSent = false;

    public function __construct(
        LoggerInterface $logger,
        private readonly ErrorRendererInterface $renderer,
        private readonly ConfigInterface $config,
        private readonly RequestInterface $request,
        private readonly SessionInterface $session,
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
                    $error['line'],
                );
                $this->handleException($exception);
            }
        });
    }

    public function markResponseSent(): void
    {
        $this->responseSent = true;
    }

    private function handleException(Throwable $e): void
    {
        if ($e instanceof ValidationException) {
            if ($this->responseSent || headers_sent()) {
                return;
            }
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

        if ($this->responseSent || headers_sent()) {
            return;
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
            $this->sendSecurityHeaders();
        }

        if ($this->isJsonRequest()) {
            $isDebug = (bool) $this->config->get('app', 'debug', false);
            Response::json([
                'error'   => true,
                'status'  => $statusCode,
                'message' => $isDebug
                    ? $e->getMessage()
                    : ($statusCode === 404 ? 'Not Found' : 'Internal Server Error'),
            ], $statusCode)->send();
            $this->responseSent = true;
            return;
        }

        $isDebug = (bool) $this->config->get('app', 'debug', false);
        echo $this->renderer->render($e, $isDebug, $statusCode);
        $this->responseSent = true;
    }

    private function isJsonRequest(): bool
    {
        $accept = strtolower((string) $this->request->header('accept', ''));
        return $this->request->isAjax()
            || str_contains($accept, 'application/json')
            || str_starts_with($this->request->uri(), '/api/');
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    private function handleValidationException(ValidationException $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            $this->sendSecurityHeaders();
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
            $this->responseSent = true;
            return;
        }

        $errors = $e->getErrors();

        try {
            $this->session->flash('_errors', $errors);

            $firstField = array_key_first($errors);
            if ($firstField !== null && !empty($errors[$firstField][0])) {
                $this->session->flash('error', $errors[$firstField][0]);
            }

            $oldInput = $this->request->all();
            unset(
                $oldInput['password'],
                $oldInput['password_confirmation'],
                $oldInput['current_password'],
                $oldInput['_csrf_token'],
            );
            $this->session->flash('_old_input', $oldInput);

            $redirectUrl = $this->resolveBackUrl();
            Response::redirect($redirectUrl, 302)->send();
        } catch (Throwable $sessionException) {
            try {
                $this->logger->error('Не удалось сохранить ошибки валидации в сессии', [
                    'exception' => $sessionException::class,
                    'message'   => $sessionException->getMessage(),
                ]);
            } catch (Throwable) {
                // Сбой логирования не должен ломать резервный HTTP-ответ.
            }
            Response::html('Сервис временно недоступен. Попробуйте позже.', 503)->send();
        }
        $this->responseSent = true;
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
