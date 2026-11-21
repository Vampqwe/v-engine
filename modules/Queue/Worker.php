<?php

declare(strict_types=1);

namespace Modules\Queue;

use Modules\Queue\Middleware\QueueMiddlewareInterface;
use Throwable;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class Worker
{
    private LoggerInterface $logger;

    /**
     * @param iterable<QueueMiddlewareInterface> $middleware
     */
    public function __construct(
        private readonly QueueDriverInterface $driver,
        private readonly JobRegistry $jobRegistry,
        private readonly FailedJobRepositoryInterface $failedJobs,
        private readonly iterable $middleware,
        private readonly TimeDateInterface $timeDate,
        LoggerInterface $logger,
        private readonly int $visibilityTimeout,
    ) {
        if ($visibilityTimeout < 1) {
            throw new QueueException('Visibility timeout должен быть положительным');
        }
        $this->logger = $logger->forModule('Queue');
    }

    /**
     * Stop time is a maximum worker lifetime; a running job is allowed to finish.
     *
     * @param callable(): bool|null $shouldStop
     */
    public function run(
        string $queue = 'default',
        int $timeout = 0,
        ?int $maxTries = null,
        ?callable $shouldStop = null,
    ): int {
        QueueName::validate($queue);
        if ($timeout < 0 || ($maxTries !== null && $maxTries < 1)) {
            throw new QueueException('Timeout не может быть отрицательным, а tries должен быть положительным');
        }

        $startedAt = hrtime(true);
        $processed = 0;

        while (true) {
            if ($shouldStop !== null && $shouldStop()) {
                break;
            }
            if ($timeout > 0 && (hrtime(true) - $startedAt) / 1_000_000_000 >= $timeout) {
                break;
            }

            if ($this->runOne($queue, $maxTries)) {
                $processed++;
                continue;
            }

            usleep($this->idleSleepMicroseconds($startedAt, $timeout));
        }

        return $processed;
    }

    public function runOne(string $queue = 'default', ?int $maxTries = null): bool
    {
        QueueName::validate($queue);
        if ($maxTries !== null && $maxTries < 1) {
            throw new QueueException('Количество попыток должно быть положительным');
        }

        $reservation = $this->driver->reserve(
            $queue,
            $this->timeDate->timestamp(),
            $this->visibilityTimeout,
        );
        if ($reservation === null) {
            return false;
        }

        try {
            $job = $this->jobRegistry->create($reservation->envelope);
        } catch (Throwable $exception) {
            $this->failedJobs->record($reservation->envelope, $exception, $this->timeDate->timestamp());
            $this->driver->acknowledge($reservation);
            $this->logger->error('В очередь попало неизвестное или некорректное задание', [
                'job_id'    => $reservation->envelope->id,
                'type'      => $reservation->envelope->type,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ]);
            return true;
        }

        $tries = $maxTries ?? $job->tries();
        if ($tries < 1) {
            throw new QueueException('Job::tries() должен возвращать положительное число');
        }

        $startedAt = hrtime(true);
        try {
            foreach ($this->middleware as $middleware) {
                $middleware->before($job, $reservation->envelope);
            }
            $job->handle();
        } catch (Throwable $exception) {
            $duration = $this->durationMilliseconds($startedAt);
            $this->handleFailure($reservation, $job, $exception, $tries, $duration);
            return true;
        }

        $duration = $this->durationMilliseconds($startedAt);
        $this->driver->acknowledge($reservation);
        foreach ($this->middleware as $middleware) {
            $middleware->after($job, $reservation->envelope, $duration);
        }

        return true;
    }

    private function handleFailure(
        QueueReservation $reservation,
        Job $job,
        Throwable $exception,
        int $tries,
        int $durationMilliseconds,
    ): void {
        $envelope = $reservation->envelope;
        $attempts = $envelope->attempts + 1;
        $willRetry = $attempts < $tries;

        foreach ($this->middleware as $middleware) {
            try {
                $middleware->failed($job, $envelope, $exception, $willRetry, $durationMilliseconds);
            } catch (Throwable $middlewareException) {
                $this->logMiddlewareFailure($middlewareException, $envelope);
            }
        }

        if ($willRetry) {
            $delay = $job->backoff($attempts);
            if ($delay < 0) {
                throw new QueueException('Job::backoff() не может возвращать отрицательное значение', $exception);
            }
            $this->driver->release(
                $reservation,
                $envelope->withAttempts($attempts, $this->timeDate->timestamp() + $delay),
            );
            return;
        }

        $this->failedJobs->record($envelope->withAttempts($attempts, $envelope->availableAt), $exception, $this->timeDate->timestamp());
        $this->driver->acknowledge($reservation);
    }

    private function durationMilliseconds(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function idleSleepMicroseconds(int $startedAt, int $timeout): int
    {
        if ($timeout === 0) {
            return 250_000;
        }
        $remaining = $timeout - ((hrtime(true) - $startedAt) / 1_000_000_000);
        return max(1, min(250_000, (int) ($remaining * 1_000_000)));
    }

    private function logMiddlewareFailure(Throwable $exception, QueueEnvelope $envelope): void
    {
        try {
            $this->logger->error('Queue middleware failed while reporting a job error', [
                'job_id'    => $envelope->id,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ]);
        } catch (Throwable $loggingException) {
            error_log('Queue middleware logging failed: ' . $loggingException->getMessage());
        }
    }
}
