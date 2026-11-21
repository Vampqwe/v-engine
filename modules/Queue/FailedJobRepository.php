<?php

declare(strict_types=1);

namespace Modules\Queue;

use JsonException;
use Throwable;

final class FailedJobRepository implements FailedJobRepositoryInterface
{
    public function __construct(private readonly string $storageDir) {}

    public function record(QueueEnvelope $envelope, Throwable $exception, int $failedAt): void
    {
        $directory = rtrim($this->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $envelope->queue;
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new QueueException("Не удалось создать каталог failed jobs: {$directory}");
        }

        try {
            $record = json_encode([
                'failed_at' => $failedAt,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
                'job'       => json_decode($envelope->toJson(), true, 512, JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $jsonException) {
            throw new QueueException('Не удалось сериализовать failed job в JSON', $jsonException);
        }

        $target = $directory . '/' . sprintf('%020d-%s.json', $failedAt, $envelope->id);
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $record, LOCK_EX) === false) {
            throw new QueueException('Не удалось записать failed job');
        }
        if (!chmod($temporary, 0o600) || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new QueueException('Не удалось атомарно сохранить failed job');
        }
    }
}
