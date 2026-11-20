<?php

declare(strict_types=1);

namespace App\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Config\ConfigInterface;
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
        private readonly ConfigInterface $config,
        private readonly SessionInterface $session,
        private readonly RouterInterface $router,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger->forModule('Security');
    }

    protected function bucket(RequestInterface $request): string
    {
        return 'global';
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $policy = $this->policy($request);
        $maxAttempts = $policy['max_attempts'];
        $decaySeconds = $policy['decay_seconds'];

        try {
            $result = $this->rateLimiter->hit($this->bucket($request), $request->ip(), $decaySeconds);
        } catch (RateLimiterException $e) {
            $this->logger->error('Ошибка хранилища RateLimit: ' . $e->getMessage());
            if ($policy['fail_open']) {
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

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            $body = $request->method() === 'HEAD'
                ? ''
                : "Слишком много запросов. Повторите попытку через {$retryAfter} сек.";

            return Response::html(
                $body,
                429,
            )
                ->withHeader('Retry-After', (string) $retryAfter)
                ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $this->session->flash('error', "Слишком много попыток. Повторите через {$retryAfter} сек.");

        $fallbackUrl = $request->uri() !== '' ? $request->uri() : $this->router->route('home');
        return $this->redirect($fallbackUrl, 302)
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    /**
     * @return array{max_attempts: int, decay_seconds: int, fail_open: bool}
     */
    private function policy(RequestInterface $request): array
    {
        $policy = $this->config->get('rate-limit', $this->bucket($request));
        if (
            !is_array($policy)
            || !is_int($policy['max_attempts'] ?? null)
            || $policy['max_attempts'] < 1
            || !is_int($policy['decay_seconds'] ?? null)
            || $policy['decay_seconds'] < 1
            || !is_bool($policy['fail_open'] ?? null)
        ) {
            throw new \LogicException('Некорректная конфигурация rate limit: ' . $this->bucket($request));
        }

        return $policy;
    }
}
