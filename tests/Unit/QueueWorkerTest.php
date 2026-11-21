<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\Container;
use Modules\Queue\FailedJobRepository;
use Modules\Queue\Job;
use Modules\Queue\JobMetrics;
use Modules\Queue\JobRegistry;
use Modules\Queue\Middleware\JobMetricsMiddleware;
use Modules\Queue\QueueDriverInterface;
use Modules\Queue\QueueEnvelope;
use Modules\Queue\QueueReservation;
use Modules\Queue\Worker;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class QueueWorkerTest extends TestCase
{
    private string $directory;
    private QueueTestClock $clock;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vengine-queue-worker-' . bin2hex(random_bytes(6));
        $this->clock = new QueueTestClock(1_800_000_000);
        QueueTestJob::$handled = 0;
        QueueTestJob::$failuresRemaining = 0;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/default/*.json') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory . '/default')) {
            rmdir($this->directory . '/default');
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testRetriesWithBackoffThenAcknowledgesSuccessfulJob(): void
    {
        QueueTestJob::$failuresRemaining = 2;
        $driver = new QueueTestDriver($this->newEnvelope());
        $metrics = new JobMetrics();
        $worker = $this->makeWorker($driver, $metrics);

        self::assertTrue($worker->runOne());
        self::assertSame(1, $driver->current?->attempts);
        self::assertSame($this->clock->timestamp() + 5, $driver->current?->availableAt);

        $this->clock->advance(5);
        self::assertTrue($worker->runOne());
        self::assertSame(2, $driver->current?->attempts);

        $this->clock->advance(10);
        self::assertTrue($worker->runOne());
        self::assertSame(1, $driver->acknowledged);
        self::assertSame([
            'succeeded'             => 1,
            'retried'               => 2,
            'failed'                => 0,
            'duration_milliseconds' => 0,
        ], $metrics->snapshot());
    }

    public function testExhaustedRetriesAreWrittenToJsonDeadLetterBeforeAcknowledgement(): void
    {
        QueueTestJob::$failuresRemaining = 3;
        $driver = new QueueTestDriver($this->newEnvelope());
        $worker = $this->makeWorker($driver, new JobMetrics());

        $worker->runOne(maxTries: 2);
        $this->clock->advance(5);
        $worker->runOne(maxTries: 2);

        $failedFiles = glob($this->directory . '/default/*.json') ?: [];
        self::assertCount(1, $failedFiles);
        $failedRecord = json_decode((string) file_get_contents($failedFiles[0]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $failedRecord['job']['attempts']);
        self::assertSame(RuntimeException::class, $failedRecord['exception']);
        self::assertSame(1, $driver->acknowledged);
        self::assertStringNotContainsString('O:', (string) file_get_contents($failedFiles[0]));
    }

    private function makeWorker(QueueTestDriver $driver, JobMetrics $metrics): Worker
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('forModule')->willReturn($logger);
        $logger->method('info');
        $logger->method('error');

        return new Worker(
            $driver,
            new JobRegistry([QueueTestJob::class], new Container()),
            new FailedJobRepository($this->directory),
            [new JobMetricsMiddleware($metrics)],
            $this->clock,
            $logger,
            30,
        );
    }

    private function newEnvelope(): QueueEnvelope
    {
        return new QueueEnvelope(
            bin2hex(random_bytes(16)),
            QueueTestJob::class,
            'default',
            ['value' => 'test'],
            0,
            $this->clock->timestamp(),
            $this->clock->timestamp(),
        );
    }
}

final class QueueTestDriver implements QueueDriverInterface
{
    public int $acknowledged = 0;
    public ?QueueEnvelope $current;
    private bool $reserved = false;

    public function __construct(QueueEnvelope $envelope)
    {
        $this->current = $envelope;
    }

    public function enqueue(QueueEnvelope $envelope): void
    {
        $this->current = $envelope;
        $this->reserved = false;
    }

    public function reserve(string $queue, int $now, int $visibilityTimeout): ?QueueReservation
    {
        if ($this->current === null || $this->reserved || $this->current->availableAt > $now) {
            return null;
        }
        $this->reserved = true;
        return new QueueReservation($this->current, 'reservation');
    }

    public function acknowledge(QueueReservation $reservation): void
    {
        $this->acknowledged++;
        $this->current = null;
        $this->reserved = false;
    }

    public function release(QueueReservation $reservation, QueueEnvelope $envelope): void
    {
        $this->current = $envelope;
        $this->reserved = false;
    }
}

final class QueueTestJob extends Job
{
    public static int $handled = 0;
    public static int $failuresRemaining = 0;

    public function __construct(private readonly array $data = []) {}

    public function handle(): void
    {
        self::$handled++;
        if (self::$failuresRemaining > 0) {
            self::$failuresRemaining--;
            throw new RuntimeException('Transient job failure');
        }
    }

    public function payload(): array
    {
        return $this->data;
    }

    public static function fromPayload(array $payload, ContainerInterface $container): static
    {
        return new self($payload);
    }

    public function tries(): int
    {
        return 3;
    }

    public function backoff(int $attempt): int
    {
        return 5 * $attempt;
    }
}

final class QueueTestClock implements TimeDateInterface
{
    public function __construct(private int $timestamp) {}

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }

    public function now(string $format = 'Y-m-d H:i:s'): string
    {
        return (new \DateTimeImmutable('@' . $this->timestamp))->format($format);
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }

    public function immutable(?string $time = 'now'): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . ($time === null || $time === 'now' ? $this->timestamp : strtotime($time)));
    }
}
