<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Http\HttpException;
use VEngine\System\Http\Request;
use VEngine\System\Map\Map;

final class RequestTest extends TestCase
{
    public function testParsesJsonUsingPhpContentTypeServerVariable(): void
    {
        $request = $this->makeRequest(
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json; charset=utf-8'],
            '{"name":"Ada"}',
        );

        self::assertSame('application/json; charset=utf-8', $request->header('content-type'));
        self::assertSame('Ada', $request->input('name'));
    }

    public function testParsesUrlEncodedPutBody(): void
    {
        $request = $this->makeRequest(
            ['REQUEST_METHOD' => 'PUT', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            'profile[name]=Ada',
        );

        self::assertSame('Ada', $request->input('profile.name'));
    }

    public function testRejectsMalformedJsonWithBadRequest(): void
    {
        try {
            $this->makeRequest(
                ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
                '{"name":',
            );
            self::fail('Malformed JSON should be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
        }
    }

    public function testUsesInjectedRemoteAddressAndTrustedForwardedAddress(): void
    {
        $request = $this->makeRequest(
            [
                'REQUEST_METHOD'       => 'GET',
                'REMOTE_ADDR'          => '10.0.0.2',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
            ],
            '',
            ['app' => ['trusted_proxies' => ['10.0.0.0/8']]],
            ['http' => ['max_body_bytes' => 5]],
        );

        self::assertSame('203.0.113.8', $request->ip());
    }

    public function testMatchesEquivalentIpv6TrustedProxyAddresses(): void
    {
        $request = $this->makeRequest(
            [
                'REQUEST_METHOD'       => 'GET',
                'REMOTE_ADDR'          => '0:0:0:0:0:0:0:1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
            ],
            '',
            ['app' => ['trusted_proxies' => ['::1']]],
        );

        self::assertSame('203.0.113.8', $request->ip());
    }

    public function testRejectsRequestBodyLargerThanConfiguredLimit(): void
    {
        try {
            $this->makeRequest(
                ['REQUEST_METHOD' => 'PUT', 'CONTENT_LENGTH' => '6'],
                '123456',
                [],
                ['http' => ['max_body_bytes' => 5]],
            );
            self::fail('Oversized request body should be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(413, $exception->getStatusCode());
        }
    }

    private function makeRequest(
        array $server,
        string $rawBody,
        array $configValues = [],
        array $additionalConfigValues = [],
    ): Request {
        $config = new class (array_merge($configValues, $additionalConfigValues)) implements ConfigInterface {
            public function __construct(private readonly array $values) {}

            public function env(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function get(string $file, string $key, mixed $default = null): mixed
            {
                return $this->values[$file][$key] ?? $default;
            }
        };

        return new Request(
            new Map(),
            $config,
            $server,
            [],
            [],
            $rawBody,
        );
    }
}
