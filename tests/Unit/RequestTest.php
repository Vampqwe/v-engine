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

    private function makeRequest(array $server, string $rawBody): Request
    {
        $config = new class implements ConfigInterface {
            public function env(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function get(string $file, string $key, mixed $default = null): mixed
            {
                return $default;
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
