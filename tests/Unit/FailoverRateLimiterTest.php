<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\RateLimit\FailoverRateLimiter;
use VEngine\System\RateLimit\FileRateLimiter;
use VEngine\System\RateLimit\RateLimitResult;
use VEngine\System\RateLimit\RateLimiterException;
use VEngine\System\RateLimit\RateLimiterInterface;
use VEngine\System\TimeDate\TimeDateInterface;
use DateTimeImmutable;

final class FailoverRateLimiterTest extends TestCase
{
    private string $directory;
    private TestClock $clock;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vengine-rate-limit-' . bin2hex(random_bytes(6));
        $this->clock = new TestClock(1_800_000_000);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testUsesFileCountersOnRedisFailureAndKeepsThemUntilTheWindowExpires(): void
    {
        $redis = new TestRedisRateLimiter();
        $redis->available = false;
        $limiter = $this->makeFailover($redis);

        self::assertSame(1, $limiter->hit('admin_login', '192.0.2.1', 300)->attempts);

        $redis->available = true;
        $fileBackendAfterRestart = new FileRateLimiter($this->directory, $this->clock);
        $limiterAfterRestart = $this->makeFailover($redis, $fileBackendAfterRestart);

        self::assertSame(2, $limiterAfterRestart->hit('admin_login', '192.0.2.1', 300)->attempts);
        self::assertSame(1, $redis->hitCalls);

        $this->clock->advance(301);

        self::assertSame(1, $limiterAfterRestart->hit('admin_login', '192.0.2.1', 300)->attempts);
        self::assertSame(2, $redis->hitCalls);
    }

    public function testFileLimiterKeepsSeparateCountersForDifferentIdentifiers(): void
    {
        $limiter = new FileRateLimiter($this->directory, $this->clock);

        self::assertSame(1, $limiter->hit('admin_login', '192.0.2.1', 300)->attempts);
        self::assertSame(1, $limiter->hit('admin_login', '192.0.2.2', 300)->attempts);
        self::assertSame(2, $limiter->hit('admin_login', '192.0.2.1', 300)->attempts);
    }

    public function testClearResetsPrimaryAndFallbackCountersWhileFallbackIsActive(): void
    {
        $redis = new TestRedisRateLimiter();
        $redis->available = false;
        $limiter = $this->makeFailover($redis);
        self::assertSame(1, $limiter->hit('admin_login', '192.0.2.1', 300)->attempts);

        $redis->available = true;
        $limiter->clear('admin_login', '192.0.2.1');

        self::assertSame(1, $redis->clearCalls);
        $this->clock->advance(301);
        self::assertSame(1, $limiter->hit('admin_login', '192.0.2.1', 300)->attempts);
    }

    private function makeFailover(
        RateLimiterInterface $redis,
        ?FileRateLimiter $fileBackend = null,
    ): FailoverRateLimiter {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('forModule')->willReturn($logger);

        return new FailoverRateLimiter(
            $redis,
            $fileBackend ?? new FileRateLimiter($this->directory, $this->clock),
            300,
            $logger,
        );
    }
}

final class TestRedisRateLimiter implements RateLimiterInterface
{
    public bool $available = true;
    public int $hitCalls = 0;
    public int $clearCalls = 0;
    private int $successfulHits = 0;

    public function hit(string $bucket, string $identifier, int $decaySeconds): RateLimitResult
    {
        $this->hitCalls++;
        if (!$this->available) {
            throw new RateLimiterException('Redis unavailable');
        }

        $this->successfulHits++;
        return new RateLimitResult($this->successfulHits, $decaySeconds);
    }

    public function clear(string $bucket, string $identifier): void
    {
        if (!$this->available) {
            throw new RateLimiterException('Redis unavailable');
        }

        $this->clearCalls++;
        $this->successfulHits = 0;
    }
}

final class TestClock implements TimeDateInterface
{
    public function __construct(private int $timestamp) {}

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }

    public function now(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->immutable()->format($format);
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }

    public function immutable(?string $time = 'now'): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $this->timestamp);
    }
}
