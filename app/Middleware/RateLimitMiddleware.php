<?php

declare(strict_types=1);

namespace App\Middleware;

use Throwable;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Middleware\AbstractMiddleware;
use VEngine\System\Redis\RedisClient;
use VEngine\System\Router\RouterInterface;
use VEngine\System\Session\SessionInterface;

class RateLimitMiddleware extends AbstractMiddleware
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly RedisClient $redis,
        private readonly SessionInterface $session,
        private readonly RouterInterface $router,
        LoggerInterface $logger
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

    /**
     * Формирует единый ключ Redis для конкретного пространства имен и IP.
     */
    public static function buildKey(string $bucket, string $ip): string
    {
        return sprintf('ratelimit:%s:%s', $bucket, sha1($ip));
    }

    /**
     * Сбрасывает счетчик попыток для указанного bucket и IP-адреса.
     */
    public function clear(string $bucket, string $ip): void
    {
        try {
            $this->redis->connection()->del(self::buildKey($bucket, $ip));
        } catch (Throwable $e) {
            $this->logger->error('Не удалось сбросить счетчик RateLimit: ' . $e->getMessage());
        }
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $maxAttempts = $this->maxAttempts();
        $decaySeconds = $this->decaySeconds();
        $key = self::buildKey($this->bucket($request), $request->ip());

        try {
            $client = $this->redis->connection();
            $attempts = (int) $client->incr($key);

            if ($attempts === 1) {
                $client->expire($key, $decaySeconds);
            }

            $ttl = (int) $client->ttl($key);
            if ($ttl < 0) {
                $client->expire($key, $decaySeconds);
                $ttl = $decaySeconds;
            }
        } catch (Throwable $e) {
            $this->logger->error('Ошибка Redis в RateLimitMiddleware: ' . $e->getMessage());
            return $next($request);
        }

        if ($attempts > $maxAttempts) {
            return $this->buildTooManyRequestsResponse($request, $maxAttempts, $ttl);
        }

        $remaining = max(0, $maxAttempts - $attempts);
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
