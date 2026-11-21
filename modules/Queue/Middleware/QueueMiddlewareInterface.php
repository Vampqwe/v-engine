<?php

declare(strict_types=1);

namespace Modules\Queue\Middleware;

use Modules\Queue\Job;
use Modules\Queue\QueueEnvelope;
use Throwable;

interface QueueMiddlewareInterface
{
    public function before(Job $job, QueueEnvelope $envelope): void;

    public function after(Job $job, QueueEnvelope $envelope, int $durationMilliseconds): void;

    public function failed(
        Job $job,
        QueueEnvelope $envelope,
        Throwable $exception,
        bool $willRetry,
        int $durationMilliseconds,
    ): void;
}
