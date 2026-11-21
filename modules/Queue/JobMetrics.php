<?php

declare(strict_types=1);

namespace Modules\Queue;

final class JobMetrics
{
    private int $succeeded = 0;
    private int $retried = 0;
    private int $failed = 0;
    private int $durationMilliseconds = 0;

    public function succeeded(int $durationMilliseconds): void
    {
        $this->succeeded++;
        $this->durationMilliseconds += max(0, $durationMilliseconds);
    }

    public function retried(int $durationMilliseconds): void
    {
        $this->retried++;
        $this->durationMilliseconds += max(0, $durationMilliseconds);
    }

    public function failed(int $durationMilliseconds): void
    {
        $this->failed++;
        $this->durationMilliseconds += max(0, $durationMilliseconds);
    }

    /**
     * @return array{succeeded: int, retried: int, failed: int, duration_milliseconds: int}
     */
    public function snapshot(): array
    {
        return [
            'succeeded'            => $this->succeeded,
            'retried'              => $this->retried,
            'failed'               => $this->failed,
            'duration_milliseconds' => $this->durationMilliseconds,
        ];
    }
}
