<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VEngine\System\Redis\RedisNamespace;

final class RedisNamespaceTest extends TestCase
{
    public function testPrefixesRedisKeysWithTheSelectedSite(): void
    {
        self::assertSame('vengine:Corporate:', RedisNamespace::forSite('vengine:', 'Corporate'));
        self::assertSame('vengine:Storefront:', RedisNamespace::forSite('vengine:', 'Storefront'));
    }

    public function testRejectsAnEmptySiteName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RedisNamespace::forSite('vengine:', '');
    }
}
