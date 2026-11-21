<?php

declare(strict_types=1);

namespace Modules\Queue;

use Throwable;

interface FailedJobRepositoryInterface
{
    public function record(QueueEnvelope $envelope, Throwable $exception, int $failedAt): void;
}
