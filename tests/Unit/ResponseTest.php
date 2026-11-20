<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VEngine\System\Http\Response;

final class ResponseTest extends TestCase
{
    public function testJsonSubstitutesInvalidUtf8(): void
    {
        $response = Response::json(['value' => "\xB1"]);
        ob_start();
        $response->send();
        $body = ob_get_clean();

        self::assertIsString($body);
        self::assertSame(['value' => "\u{FFFD}"], json_decode($body, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRejectsLineBreaksInHeaderValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Response::html('ok')->withHeader('X-Test', "safe\r\nInjected: true");
    }
}
