<?php

declare(strict_types=1);

namespace Modules\Queue;

use JsonException;

final class QueueEnvelope
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $queue,
        public readonly array $payload,
        public readonly int $attempts,
        public readonly int $createdAt,
        public readonly int $availableAt,
    ) {
        QueueName::validate($queue);
        if ($id === '' || $type === '' || $attempts < 0 || $createdAt < 0 || $availableAt < 0) {
            throw new QueueException('Некорректный конверт задания очереди');
        }
    }

    public static function forJob(Job $job, int $now, int $delay = 0): self
    {
        QueueName::validate($job->queue());
        return new self(
            bin2hex(random_bytes(16)),
            $job::class,
            $job->queue(),
            $job->payload(),
            0,
            $now,
            $now + $delay,
        );
    }

    public function withAttempts(int $attempts, int $availableAt): self
    {
        return new self(
            $this->id,
            $this->type,
            $this->queue,
            $this->payload,
            $attempts,
            $this->createdAt,
            $availableAt,
        );
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode([
            'id'           => $this->id,
            'type'         => $this->type,
            'queue'        => $this->queue,
            'payload'      => $this->payload,
            'attempts'     => $this->attempts,
            'created_at'   => $this->createdAt,
            'available_at' => $this->availableAt,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (
            !is_array($data)
            || !isset($data['id'], $data['type'], $data['queue'], $data['payload'], $data['attempts'])
            || !is_string($data['id'])
            || !is_string($data['type'])
            || !is_string($data['queue'])
            || !is_array($data['payload'])
            || !is_int($data['attempts'])
            || !isset($data['created_at'], $data['available_at'])
            || !is_int($data['created_at'])
            || !is_int($data['available_at'])
        ) {
            throw new QueueException('Некорректный JSON-конверт задания');
        }

        return new self(
            $data['id'],
            $data['type'],
            $data['queue'],
            $data['payload'],
            $data['attempts'],
            $data['created_at'],
            $data['available_at'],
        );
    }
}
