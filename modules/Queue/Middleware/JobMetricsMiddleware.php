<?php

declare(strict_types=1);

namespace Modules\Queue\Middleware;

use Modules\Queue\Job;
use Modules\Queue\JobMetrics;
use Modules\Queue\QueueEnvelope;
use Throwable;

final class JobMetricsMiddleware implements QueueMiddlewareInterface
{
    public function __construct(private readonly JobMetrics $metrics) {}

    public function before(Job $job, QueueEnvelope $envelope): void {}

    public function after(Job $job, QueueEnvelope $envelope, int $durationMilliseconds): void
    {
        $this->metrics->succeeded($durationMilliseconds);
    }

    public function failed(
        Job $job,
        QueueEnvelope $envelope,
        Throwable $exception,
        bool $willRetry,
        int $durationMilliseconds,
    ): void {
        if ($willRetry) {
            $this->metrics->retried($durationMilliseconds);
            return;
        }
        $this->metrics->failed($durationMilliseconds);
    }
}
