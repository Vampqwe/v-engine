<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\RateLimitMiddleware;
use Modules\Admin\Middleware\LoginRateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use VEngine\System\Config\Config;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Map\Map;
use VEngine\System\RateLimit\RateLimiterException;
use VEngine\System\RateLimit\RateLimiterInterface;
use VEngine\System\RateLimit\RateLimitResult;
use VEngine\System\Router\RouterInterface;
use VEngine\System\Session\SessionInterface;

final class RateLimitMiddlewareTest extends TestCase
{
    public function testGeneralRateLimitFailsOpenWhenRedisIsUnavailable(): void
    {
        $middleware = $this->makeMiddleware(RateLimitMiddleware::class);
        $nextCalled = false;

        $response = $middleware->process(
            $this->makeRequest(),
            static function () use (&$nextCalled): Response {
                $nextCalled = true;
                return Response::html('ok');
            },
        );

        self::assertTrue($nextCalled);
        self::assertSame(200, $this->sendAndGetStatus($response));
    }

    public function testAdminLoginFailsClosedWhenRedisIsUnavailable(): void
    {
        $middleware = $this->makeMiddleware(LoginRateLimitMiddleware::class);
        $nextCalled = false;

        $response = $middleware->process(
            $this->makeRequest(),
            static function () use (&$nextCalled): Response {
                $nextCalled = true;
                return Response::html('ok');
            },
        );

        self::assertFalse($nextCalled);
        self::assertSame(503, $this->sendAndGetStatus($response));
    }

    public function testHtmlGetRateLimitReturns429InsteadOfRedirectingToTheSameUri(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('hit')->willReturn(new RateLimitResult(61, 30));
        $middleware = $this->makeMiddleware(RateLimitMiddleware::class, $rateLimiter);
        $nextCalled = false;

        $response = $middleware->process(
            $this->makeRequest('GET', '/api/health', ['accept' => 'text/html']),
            static function () use (&$nextCalled): Response {
                $nextCalled = true;
                return Response::html('ok');
            },
        );

        self::assertFalse($nextCalled);
        self::assertSame(429, $this->sendAndGetStatus($response));
    }

    /**
     * @param class-string<RateLimitMiddleware> $middlewareClass
     */
    private function makeMiddleware(
        string $middlewareClass,
        ?RateLimiterInterface $rateLimiter = null,
    ): RateLimitMiddleware {
        if ($rateLimiter === null) {
            $rateLimiter = $this->createMock(RateLimiterInterface::class);
            $rateLimiter->method('hit')->willThrowException(new RateLimiterException('Redis unavailable'));
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('forModule')->willReturn($logger);

        return new $middlewareClass(
            $rateLimiter,
            $this->makeConfig(),
            $this->createMock(SessionInterface::class),
            $this->createMock(RouterInterface::class),
            $logger,
        );
    }

    private function makeConfig(): Config
    {
        return new Config(dirname(__DIR__, 2), new Map());
    }

    private function makeRequest(
        string $method = 'POST',
        string $uri = '/admin/login',
        array $headers = [],
    ): RequestInterface {
        return new class ($method, $uri, $headers) implements RequestInterface {
            public function __construct(
                private readonly string $requestMethod,
                private readonly string $requestUri,
                private readonly array $requestHeaders,
            ) {}

            public function method(): string
            {
                return $this->requestMethod;
            }

            public function uri(): string
            {
                return $this->requestUri;
            }

            public function query(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function input(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function all(): array
            {
                return [];
            }

            public function header(string $name, mixed $default = null): mixed
            {
                return $this->requestHeaders[strtolower($name)] ?? $default;
            }

            public function isAjax(): bool
            {
                return false;
            }

            public function withAttribute(string $name, mixed $value): self
            {
                return $this;
            }

            public function attribute(string $name, mixed $default = null): mixed
            {
                return $default;
            }

            public function ip(): string
            {
                return '127.0.0.1';
            }
        };
    }

    private function sendAndGetStatus(Response $response): int
    {
        ob_start();
        try {
            $response->send();
            return http_response_code();
        } finally {
            ob_end_clean();
        }
    }
}
