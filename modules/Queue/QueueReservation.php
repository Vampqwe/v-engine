<?php

declare(strict_types=1);

namespace Modules\Queue;

final class QueueReservation
{
    public function __construct(
        public readonly QueueEnvelope $envelope,
        public readonly string $token,
    ) {}
}
