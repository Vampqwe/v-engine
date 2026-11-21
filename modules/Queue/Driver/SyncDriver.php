<?php

declare(strict_types=1);

namespace Modules\Queue\Driver;

use JsonException;
use Modules\Queue\Job;
use Modules\Queue\Middleware\QueueMiddlewareInterface;
use Modules\Queue\QueueEnvelope;
use Modules\Queue\QueueException;
use Modules\Queue\QueueInterface;
use Throwable;
use VEngine\System\TimeDate\TimeDateInterface;

final class SyncDriver implements QueueInterface
{
    /**
     * @param iterable<QueueMiddlewareInterface> $middleware
     */
    public function __construct(
        private readonly iterable $middleware,
        private readonly TimeDateInterface $timeDate,
    ) {}

    public function push(Job $job): string
    {
        return $this->pushDelayed($job, 0);
    }

    public function pushDelayed(Job $job, int $seconds): string
    {
        if ($seconds < 0) {
            throw new QueueException('Задержка задания не может быть отрицательной');
        }
        if ($seconds > 0) {
            throw new QueueException('Sync queue не поддерживает отложенный запуск');
        }

        try {
            $envelope = QueueEnvelope::forJob($job, $this->timeDate->timestamp());
            $envelope->toJson();
        } catch (JsonException $exception) {
            throw new QueueException('Payload задания должен быть сериализуем в JSON', $exception);
        }

        $startedAt = hrtime(true);
        try {
            foreach ($this->middleware as $middleware) {
                $middleware->before($job, $envelope);
            }
            $job->handle();
            $duration = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        } catch (Throwable $exception) {
            $duration = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            foreach ($this->middleware as $middleware) {
                try {
                    $middleware->failed($job, $envelope, $exception, false, $duration);
                } catch (Throwable $middlewareException) {
                    error_log('Queue middleware failed while reporting a sync job error: ' . $middlewareException->getMessage());
                }
            }
            throw $exception;
        }

        foreach ($this->middleware as $middleware) {
            $middleware->after($job, $envelope, $duration);
        }
        return $envelope->id;
    }
}
