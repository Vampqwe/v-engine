<?php

declare(strict_types=1);

namespace Modules\Queue;

interface QueueDriverInterface
{
    public function enqueue(QueueEnvelope $envelope): void;

    public function reserve(string $queue, int $now, int $visibilityTimeout): ?QueueReservation;

    public function acknowledge(QueueReservation $reservation): void;

    public function release(QueueReservation $reservation, QueueEnvelope $envelope): void;
}
