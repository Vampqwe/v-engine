<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\Container;
use Modules\Queue\JobRegistry;
use Modules\Queue\QueueEnvelope;
use Modules\Queue\QueueException;
use PHPUnit\Framework\TestCase;

final class QueueEnvelopeTest extends TestCase
{
    public function testRoundTripsPayloadAsJsonWithoutPhpSerialization(): void
    {
        $envelope = new QueueEnvelope(
            '0123456789abcdef0123456789abcdef',
            QueueTestJob::class,
            'default',
            ['message' => 'hello'],
            2,
            100,
            110,
        );

        $json = $envelope->toJson();
        $decoded = QueueEnvelope::fromJson($json);

        self::assertSame($envelope->id, $decoded->id);
        self::assertSame($envelope->payload, $decoded->payload);
        self::assertStringNotContainsString('O:', $json);
    }

    public function testRejectsInvalidQueueNameAndMalformedEnvelope(): void
    {
        $this->expectException(QueueException::class);
        QueueEnvelope::fromJson('{"id":"id","type":"Job","queue":"../admin","payload":[],"attempts":0,"created_at":1,"available_at":1}');
    }

    public function testRegistryRejectsJobTypesOutsideTheConfiguredAllowlist(): void
    {
        $registry = new JobRegistry([], new Container());
        $envelope = new QueueEnvelope(
            '0123456789abcdef0123456789abcdef',
            \stdClass::class,
            'default',
            [],
            0,
            100,
            100,
        );

        $this->expectException(QueueException::class);
        $registry->create($envelope);
    }
}
