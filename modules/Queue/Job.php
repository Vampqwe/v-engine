<?php

declare(strict_types=1);

namespace Modules\Queue;

use Psr\Container\ContainerInterface;

abstract class Job
{
    abstract public function handle(): void;

    /**
     * @return array<string, mixed>
     */
    abstract public function payload(): array;

    /**
     * @param array<string, mixed> $payload
     */
    abstract public static function fromPayload(array $payload, ContainerInterface $container): static;

    public function queue(): string
    {
        return 'default';
    }

    public function tries(): int
    {
        return 3;
    }

    public function backoff(int $attempt): int
    {
        return min(300, 2 ** max(0, $attempt - 1));
    }
}
