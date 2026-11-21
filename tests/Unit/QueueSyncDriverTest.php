<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DI\Container;
use Modules\Queue\Driver\SyncDriver;
use Modules\Queue\Job;
use Modules\Queue\Middleware\QueueMiddlewareInterface;
use Modules\Queue\QueueException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use VEngine\System\TimeDate\TimeDateInterface;

final class QueueSyncDriverTest extends TestCase
{
    protected function setUp(): void
    {
        QueueSyncTestJob::$handled = 0;
    }

    public function testPushExecutesImmediatelyAndDelayedPushIsRejected(): void
    {
        $driver = new SyncDriver([], new QueueSyncTestClock());
        $id = $driver->push(new QueueSyncTestJob());

        self::assertSame(1, QueueSyncTestJob::$handled);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $id);

        $this->expectException(QueueException::class);
        $driver->pushDelayed(new QueueSyncTestJob(), 1);
    }

    public function testAfterMiddlewareFailureDoesNotMarkCompletedJobAsFailed(): void
    {
        $middleware = $this->createMock(QueueMiddlewareInterface::class);
        $middleware->expects(self::once())
            ->method('after')
            ->willThrowException(new RuntimeException('metrics unavailable'));
        $middleware->expects(self::never())->method('failed');
        $driver = new SyncDriver([$middleware], new QueueSyncTestClock());

        try {
            $driver->push(new QueueSyncTestJob());
            self::fail('Expected middleware exception');
        } catch (RuntimeException $exception) {
            self::assertSame('metrics unavailable', $exception->getMessage());
        }

        self::assertSame(1, QueueSyncTestJob::$handled);
    }
}

final class QueueSyncTestJob extends Job
{
    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;
    }

    public function payload(): array
    {
        return [];
    }

    public static function fromPayload(array $payload, ContainerInterface $container): static
    {
        return new self();
    }
}

final class QueueSyncTestClock implements TimeDateInterface
{
    public function now(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->immutable()->format($format);
    }

    public function timestamp(): int
    {
        return 1_800_000_000;
    }

    public function immutable(?string $time = 'now'): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . ($time === null || $time === 'now' ? $this->timestamp() : strtotime($time)));
    }
}
