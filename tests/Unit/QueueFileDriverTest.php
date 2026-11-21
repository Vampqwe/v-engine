<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Queue\Driver\FileDriver;
use Modules\Queue\QueueEnvelope;
use PHPUnit\Framework\TestCase;

final class QueueFileDriverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/vengine-queue-file-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/default/ready/*') ?: [] as $path) {
            unlink($path);
        }
        foreach (glob($this->directory . '/default/processing/*') ?: [] as $path) {
            unlink($path);
        }
        foreach ([
            $this->directory . '/default/ready',
            $this->directory . '/default/processing',
            $this->directory . '/default',
            $this->directory,
        ] as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testDelaysThenReservesAndAcknowledgesJsonJob(): void
    {
        $driver = new FileDriver($this->directory);
        $envelope = new QueueEnvelope(
            '0123456789abcdef0123456789abcdef',
            QueueTestJob::class,
            'default',
            ['message' => 'hello'],
            0,
            100,
            110,
        );
        $driver->enqueue($envelope);

        self::assertNull($driver->reserve('default', 109, 60));
        $reservation = $driver->reserve('default', 110, 60);
        self::assertNotNull($reservation);
        self::assertSame($envelope->id, $reservation->envelope->id);
        self::assertFileExists($reservation->token);

        $driver->acknowledge($reservation);
        self::assertFileDoesNotExist($reservation->token);
    }

    public function testExpiredReservationIsMadeAvailableAgain(): void
    {
        $driver = new FileDriver($this->directory);
        $envelope = new QueueEnvelope(
            'abcdef0123456789abcdef0123456789',
            QueueTestJob::class,
            'default',
            [],
            0,
            100,
            100,
        );
        $driver->enqueue($envelope);
        $firstReservation = $driver->reserve('default', 100, 10);
        self::assertNotNull($firstReservation);

        $secondReservation = $driver->reserve('default', 111, 10);

        self::assertNotNull($secondReservation);
        self::assertSame($envelope->id, $secondReservation->envelope->id);
        self::assertNotSame($firstReservation->token, $secondReservation->token);
    }
}
