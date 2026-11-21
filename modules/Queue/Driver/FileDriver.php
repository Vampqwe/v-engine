<?php

declare(strict_types=1);

namespace Modules\Queue\Driver;

use JsonException;
use Modules\Queue\QueueDriverInterface;
use Modules\Queue\QueueEnvelope;
use Modules\Queue\QueueException;
use Modules\Queue\QueueName;
use Modules\Queue\QueueReservation;

final class FileDriver implements QueueDriverInterface
{
    public function __construct(private readonly string $storageDir) {}

    public function enqueue(QueueEnvelope $envelope): void
    {
        $directory = $this->queueDirectory($envelope->queue);
        $this->ensureDirectory($directory . '/ready');
        $this->ensureDirectory($directory . '/processing');
        $this->writeReadyEnvelope($directory, $envelope);
    }

    public function reserve(string $queue, int $now, int $visibilityTimeout): ?QueueReservation
    {
        QueueName::validate($queue);
        if ($visibilityTimeout < 1) {
            throw new QueueException('Visibility timeout должен быть положительным');
        }

        $directory = $this->queueDirectory($queue);
        $this->ensureDirectory($directory . '/ready');
        $this->ensureDirectory($directory . '/processing');
        $this->requeueExpiredReservations($directory, $now);

        $readyFiles = glob($directory . '/ready/*.json') ?: [];
        sort($readyFiles, SORT_STRING);
        foreach ($readyFiles as $readyPath) {
            $filename = basename($readyPath);
            if (preg_match('/\A([0-9]{20})-([a-f0-9]{32})\.json\z/', $filename, $matches) !== 1) {
                continue;
            }
            if ((int) $matches[1] > $now) {
                break;
            }

            $processingPath = sprintf(
                '%s/processing/%020d-%s.json',
                $directory,
                $now + $visibilityTimeout,
                $matches[2],
            );
            if (!@rename($readyPath, $processingPath)) {
                continue;
            }

            try {
                $json = file_get_contents($processingPath);
                if ($json === false) {
                    throw new QueueException('Не удалось прочитать зарезервированное задание');
                }
                return new QueueReservation(QueueEnvelope::fromJson($json), $processingPath);
            } catch (JsonException $exception) {
                throw new QueueException('Повреждён JSON зарезервированного задания', $exception);
            }
        }

        return null;
    }

    public function acknowledge(QueueReservation $reservation): void
    {
        if (!is_file($reservation->token) || !unlink($reservation->token)) {
            throw new QueueException('Не удалось подтвердить обработку задания файловой очереди');
        }
    }

    public function release(QueueReservation $reservation, QueueEnvelope $envelope): void
    {
        $directory = $this->queueDirectory($envelope->queue);
        $this->writeReadyEnvelope($directory, $envelope);
        if (!is_file($reservation->token) || !unlink($reservation->token)) {
            throw new QueueException('Задание повторно поставлено, но не удалось убрать его reservation-файл');
        }
    }

    private function requeueExpiredReservations(string $directory, int $now): void
    {
        $processingFiles = glob($directory . '/processing/*.json') ?: [];
        foreach ($processingFiles as $processingPath) {
            $filename = basename($processingPath);
            if (preg_match('/\A([0-9]{20})-([a-f0-9]{32})\.json\z/', $filename, $matches) !== 1) {
                continue;
            }
            if ((int) $matches[1] > $now) {
                continue;
            }

            $json = @file_get_contents($processingPath);
            if ($json === false) {
                continue;
            }

            try {
                $envelope = QueueEnvelope::fromJson($json);
            } catch (JsonException|QueueException) {
                continue;
            }

            $this->writeReadyEnvelope($directory, $envelope);
            @unlink($processingPath);
        }
    }

    private function writeReadyEnvelope(string $directory, QueueEnvelope $envelope): void
    {
        $readyDirectory = $directory . '/ready';
        $target = sprintf(
            '%s/%020d-%s.json',
            $readyDirectory,
            $envelope->availableAt,
            $envelope->id,
        );
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';

        try {
            $json = $envelope->toJson();
        } catch (JsonException $exception) {
            throw new QueueException('Payload задания должен быть сериализуем в JSON', $exception);
        }

        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new QueueException('Не удалось записать задание в файловую очередь');
        }
        if (!chmod($temporary, 0o600) || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new QueueException('Не удалось атомарно сохранить задание в файловую очередь');
        }
    }

    private function queueDirectory(string $queue): string
    {
        QueueName::validate($queue);
        return rtrim($this->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $queue;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (!mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new QueueException("Не удалось создать каталог файловой очереди: {$directory}");
        }
    }
}
