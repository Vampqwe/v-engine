<?php

declare(strict_types=1);

namespace VEngine\System\Monitoring;

final class RequestMetrics
{
    private int $requestStartedAt = 0;
    private array $components = [];

    public function startRequest(?int $startedAt = null): void
    {
        $this->requestStartedAt = $startedAt ?? hrtime(true);
        $this->components = [];
    }

    public function record(string $component, int $durationNanoseconds): void
    {
        $this->components[$component] ??= ['count' => 0, 'duration' => 0];
        $this->components[$component]['count']++;
        $this->components[$component]['duration'] += max(0, $durationNanoseconds);
    }

    public function serverTiming(): string
    {
        $timings = [];
        if ($this->requestStartedAt > 0) {
            $timings[] = sprintf('app;dur=%.2f', (hrtime(true) - $this->requestStartedAt) / 1_000_000);
        }

        foreach ($this->components as $component => $measurement) {
            $timings[] = sprintf(
                '%s;dur=%.2f;desc="%d calls"',
                $component,
                $measurement['duration'] / 1_000_000,
                $measurement['count'],
            );
        }

        return implode(', ', $timings);
    }
}
