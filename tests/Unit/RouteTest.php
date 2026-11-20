<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Router\Route;

final class RouteTest extends TestCase
{
    public function testLiteralPunctuationIsMatchedExactly(): void
    {
        $route = new Route('GET', '/assets/{name}.json', static fn(): null => null);
        $params = [];

        self::assertTrue($route->matches('GET', '/assets/report.json', $params));
        self::assertSame(['name' => 'report'], $params);
        self::assertFalse($route->matches('GET', '/assets/reportXjson'));
    }

    public function testParameterConstraintsRemainActive(): void
    {
        $route = new Route('GET', '/users/{id:\\d+}', static fn(): null => null);

        self::assertTrue($route->matches('GET', '/users/42'));
        self::assertFalse($route->matches('GET', '/users/alice'));
    }

    public function testParameterConstraintCanContainRegexDelimiter(): void
    {
        $route = new Route('GET', '/tags/{tag:[^#]+}', static fn(): null => null);

        self::assertTrue($route->matches('GET', '/tags/php'));
    }
}
