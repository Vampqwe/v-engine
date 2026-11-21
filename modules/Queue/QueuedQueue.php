<?php

declare(strict_types=1);

namespace Modules\Queue;

use JsonException;
use VEngine\System\TimeDate\TimeDateInterface;

final class QueuedQueue implements QueueInterface
{
    public function __construct(
        private readonly QueueDriverInterface $driver,
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

        try {
            $envelope = QueueEnvelope::forJob($job, $this->timeDate->timestamp(), $seconds);
            $envelope->toJson();
            $this->driver->enqueue($envelope);
        } catch (JsonException $exception) {
            throw new QueueException('Payload задания должен быть сериализуем в JSON', $exception);
        }

        return $envelope->id;
    }
}
