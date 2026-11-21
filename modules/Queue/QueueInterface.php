<?php

declare(strict_types=1);

namespace Modules\Queue;

interface QueueInterface
{
    public function push(Job $job): string;

    public function pushDelayed(Job $job, int $seconds): string;
}
