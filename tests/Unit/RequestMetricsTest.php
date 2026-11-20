<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Monitoring\RequestMetrics;

final class RequestMetricsTest extends TestCase
{
    public function testServerTimingReportsApplicationAndAggregatedDependencies(): void
    {
        $metrics = new RequestMetrics();
        $metrics->startRequest();
        $metrics->record('db', 2_000_000);
        $metrics->record('db', 3_000_000);
        $metrics->record('redis', 1_000_000);

        $timing = $metrics->serverTiming();

        self::assertStringContainsString('app;dur=', $timing);
        self::assertStringContainsString('db;dur=5.00;desc="2 calls"', $timing);
        self::assertStringContainsString('redis;dur=1.00;desc="1 calls"', $timing);
    }

    public function testStartingAnotherRequestClearsPreviousMeasurements(): void
    {
        $metrics = new RequestMetrics();
        $metrics->startRequest();
        $metrics->record('db', 1_000_000);

        $metrics->startRequest();

        self::assertStringNotContainsString('db;', $metrics->serverTiming());
    }
}
