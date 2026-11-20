<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sites\Corporate\App\Middleware\AuthMiddleware;
use VEngine\System\Config\Config;
use VEngine\System\Http\Request;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Map\Map;

final class AuthMiddlewareTest extends TestCase
{
    private array $previousToken;
    private string $token;

    protected function setUp(): void
    {
        $this->previousToken = [
            'envExists'    => array_key_exists('API_TOKEN', $_ENV),
            'envValue'     => $_ENV['API_TOKEN'] ?? null,
            'serverExists' => array_key_exists('API_TOKEN', $_SERVER),
            'serverValue'  => $_SERVER['API_TOKEN'] ?? null,
        ];
        $this->token = bin2hex(random_bytes(32));
        $_ENV['API_TOKEN'] = $this->token;
        unset($_SERVER['API_TOKEN']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['API_TOKEN'], $_SERVER['API_TOKEN']);
        if ($this->previousToken['envExists']) {
            $_ENV['API_TOKEN'] = $this->previousToken['envValue'];
        }
        if ($this->previousToken['serverExists']) {
            $_SERVER['API_TOKEN'] = $this->previousToken['serverValue'];
        }
    }

    public function testRejectsAnArbitraryAuthorizationHeader(): void
    {
        $nextCalled = false;
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('anything'),
            static function () use (&$nextCalled): ResponseInterface {
                $nextCalled = true;
                return Response::json(['unexpected' => 'allowed']);
            },
        );

        [$status, $body] = $this->sendAndDecode($response);

        self::assertFalse($nextCalled);
        self::assertSame(401, $status);
        self::assertTrue($body['error']);
    }

    public function testAcceptsMatchingBearerTokenWithoutPassingItDownstream(): void
    {
        $passedRequest = null;
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('Bearer ' . $this->token),
            static function (RequestInterface $request) use (&$passedRequest): ResponseInterface {
                $passedRequest = $request;
                return Response::json(['ok' => true]);
            },
        );

        [$status, $body] = $this->sendAndDecode($response);

        self::assertSame(200, $status);
        self::assertTrue($body['ok']);
        self::assertInstanceOf(RequestInterface::class, $passedRequest);
        self::assertNull($passedRequest->attribute('auth.token'));
    }

    public function testFailsClosedWhenApiTokenIsNotConfigured(): void
    {
        $_ENV['API_TOKEN'] = '';
        $nextCalled = false;
        $response = $this->makeMiddleware()->process(
            $this->makeRequest('Bearer ' . $this->token),
            static function () use (&$nextCalled): ResponseInterface {
                $nextCalled = true;
                return Response::json(['unexpected' => 'allowed']);
            },
        );

        [$status] = $this->sendAndDecode($response);

        self::assertFalse($nextCalled);
        self::assertSame(503, $status);
    }

    private function makeMiddleware(): AuthMiddleware
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('forModule')->willReturn($logger);

        return new AuthMiddleware(
            new Config(dirname(__DIR__, 2), new Map()),
            $logger,
        );
    }

    private function makeRequest(?string $authorization): Request
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/api/profile',
            'HTTP_ACCEPT'    => 'application/json',
        ];
        if ($authorization !== null) {
            $server['HTTP_AUTHORIZATION'] = $authorization;
        }

        return new Request(new Map(), new Config(dirname(__DIR__, 2), new Map()), $server, [], [], '');
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function sendAndDecode(ResponseInterface $response): array
    {
        ob_start();
        try {
            $response->send();
            $body = (string) ob_get_contents();
            return [http_response_code(), json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
        } finally {
            ob_end_clean();
        }
    }
}
