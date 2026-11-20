<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use VEngine\System\Error\ErrorRenderer;

final class ErrorRendererTest extends TestCase
{
    public function testProductionPageUsesTheActualHttpStatus(): void
    {
        $html = (new ErrorRenderer())->render(new RuntimeException('Not found'), false, 404);

        self::assertStringContainsString('<title>404</title>', $html);
        self::assertStringContainsString('<h1 style="color:#f85149;font-size:48px;margin:0;">404</h1>', $html);
        self::assertStringNotContainsString('<title>500</title>', $html);
    }
}
