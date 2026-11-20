<?php

declare(strict_types=1);

namespace Tests\Unit;

use Closure;
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
        foreach (glob($this->directory . '/*/*.json') ?: [] as $path) {
            unlink($path);
        }
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            rmdir($path);
        }
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_file($this->directory . '/.gc.lock')) {
            unlink($this->directory . '/.gc.lock');
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

    public function testReadOnlyFailoverCheckDoesNotCreateStorageForMissingState(): void
    {
        $limiter = new FileRateLimiter($this->directory, $this->clock);

        self::assertFalse($limiter->isFailoverActive('admin_login', '192.0.2.1'));
        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testGarbageCollectionProcessesAtMostTheConfiguredScanBudget(): void
    {
        $limiter = new FileRateLimiter($this->directory, $this->clock);
        $cutoff = $this->clock->timestamp() - 86400;
        $state = json_encode([
            'attempts'          => 1,
            'window_started_at' => $cutoff - 1,
            'fallback_until'    => $cutoff - 1,
        ], JSON_THROW_ON_ERROR);

        for ($index = 0; $index < 250; $index++) {
            $identifier = '192.0.2.' . $index;
            $hash = hash('sha256', "admin_login\0{$identifier}");
            $directory = $this->directory . '/' . substr($hash, 0, 2);
            if (!is_dir($directory)) {
                mkdir($directory, 0o700, true);
            }
            $path = $directory . '/' . $hash . '.json';
            file_put_contents($path, $state);
            touch($path, $cutoff - 1);
        }

        $method = new \ReflectionMethod(FileRateLimiter::class, 'collectExpiredStates');

        self::assertSame(200, $method->invoke($limiter));
    }

    public function testGarbageCollectorRemovesExpiredShardedState(): void
    {
        $limiter = new FileRateLimiter($this->directory, $this->clock);
        $hash = hash('sha256', "admin_login\0192.0.2.3");
        $directory = $this->directory . '/' . substr($hash, 0, 2);
        mkdir($directory, 0o700, true);
        $path = $directory . '/' . $hash . '.json';
        file_put_contents($path, json_encode([
            'attempts'          => 1,
            'window_started_at' => $this->clock->timestamp() - 86401,
            'fallback_until'    => $this->clock->timestamp() - 1,
        ], JSON_THROW_ON_ERROR));

        $method = new \ReflectionMethod(FileRateLimiter::class, 'collectExpiredStates');

        self::assertSame(1, $method->invoke($limiter));
        self::assertFileDoesNotExist($path);
    }

    public function testRedisFallbackUsesConfiguredHoldDurationInsteadOfBucketWindow(): void
    {
        $redis = new TestRedisRateLimiter();
        $redis->available = false;
        $limiter = $this->makeFailover($redis);

        self::assertSame(1, $limiter->hit('global', '192.0.2.1', 60)->attempts);

        $redis->available = true;
        $this->clock->advance(61);

        self::assertSame(1, $limiter->hit('global', '192.0.2.1', 60)->attempts);
        self::assertSame(1, $redis->hitCalls);
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

    public function testDoesNotCountSuccessfulPrimaryHitAgainWhenFailoverActivatesConcurrently(): void
    {
        $redis = new TestRedisRateLimiter();
        $fallback = new FileRateLimiter($this->directory, $this->clock);
        $redis->afterHit = static function () use ($fallback): void {
            $fallback->activateFailover('admin_login', '192.0.2.1', 300);
        };
        $limiter = $this->makeFailover($redis, $fallback);

        self::assertSame(1, $limiter->hit('admin_login', '192.0.2.1', 300)->attempts);
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
    public ?Closure $afterHit = null;
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
        if ($this->afterHit !== null) {
            ($this->afterHit)();
        }
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
