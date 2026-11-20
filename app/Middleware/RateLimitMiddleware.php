<?php

declare(strict_types=1);

namespace App\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Middleware\AbstractMiddleware;
use VEngine\System\RateLimit\RateLimiterException;
use VEngine\System\RateLimit\RateLimiterInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\Session\SessionInterface;

class RateLimitMiddleware extends AbstractMiddleware
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
        private readonly SessionInterface $session,
        private readonly RouterInterface $router,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger->forModule('Security');
    }

    protected function maxAttempts(): int
    {
        return 60;
    }

    protected function decaySeconds(): int
    {
        return 60;
    }

    protected function bucket(RequestInterface $request): string
    {
        return 'global';
    }

    protected function failOpen(): bool
    {
        return true;
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $maxAttempts = $this->maxAttempts();
        $decaySeconds = $this->decaySeconds();

        try {
            $result = $this->rateLimiter->hit($this->bucket($request), $request->ip(), $decaySeconds);
        } catch (RateLimiterException $e) {
            $this->logger->error('Ошибка хранилища RateLimit: ' . $e->getMessage());
            if ($this->failOpen()) {
                return $next($request);
            }

            return $this->isJsonRequest($request)
                ? $this->jsonError('Сервис временно недоступен. Попробуйте позже.', 503)
                : Response::html('Сервис временно недоступен. Попробуйте позже.', 503);
        }

        if ($result->attempts > $maxAttempts) {
            return $this->buildTooManyRequestsResponse($request, $maxAttempts, $result->retryAfter);
        }

        $remaining = max(0, $maxAttempts - $result->attempts);
        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function buildTooManyRequestsResponse(RequestInterface $request, int $maxAttempts, int $retryAfter): ResponseInterface
    {
        $this->logger->info('Превышен лимит запросов (Rate Limit)', [
            'ip'     => $request->ip(),
            'method' => $request->method(),
            'uri'    => $request->uri(),
            'bucket' => $this->bucket($request),
        ]);

        if ($this->isJsonRequest($request)) {
            return $this->jsonError("Слишком много запросов. Повторите попытку через {$retryAfter} сек.", 429)
                ->withHeader('Retry-After', (string) $retryAfter)
                ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $this->session->flash('error', "Слишком много попыток. Повторите через {$retryAfter} сек.");

        $fallbackUrl = $request->uri() !== '' ? $request->uri() : $this->router->route('home');
        return $this->redirect($fallbackUrl, 302)
            ->withHeader('Retry-After', (string) $retryAfter);
    }
}
