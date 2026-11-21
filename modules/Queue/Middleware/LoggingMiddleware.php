<?php

declare(strict_types=1);

namespace Modules\Queue\Middleware;

use Modules\Queue\Job;
use Modules\Queue\QueueEnvelope;
use Throwable;
use VEngine\System\Logger\LoggerInterface;

final class LoggingMiddleware implements QueueMiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger->forModule('Queue');
    }

    public function before(Job $job, QueueEnvelope $envelope): void
    {
        $this->logger->info('Начата обработка задания очереди', [
            'job_id' => $envelope->id,
            'type'   => $envelope->type,
            'queue'  => $envelope->queue,
            'attempt' => $envelope->attempts + 1,
        ]);
    }

    public function after(Job $job, QueueEnvelope $envelope, int $durationMilliseconds): void
    {
        $this->logger->info('Задание очереди обработано', [
            'job_id'   => $envelope->id,
            'type'     => $envelope->type,
            'duration' => $durationMilliseconds,
        ]);
    }

    public function failed(
        Job $job,
        QueueEnvelope $envelope,
        Throwable $exception,
        bool $willRetry,
        int $durationMilliseconds,
    ): void {
        $this->logger->error('Ошибка обработки задания очереди', [
            'job_id'    => $envelope->id,
            'type'      => $envelope->type,
            'attempt'   => $envelope->attempts + 1,
            'will_retry' => $willRetry,
            'duration'  => $durationMilliseconds,
            'exception' => $exception::class,
            'message'   => $exception->getMessage(),
        ]);
    }
}
