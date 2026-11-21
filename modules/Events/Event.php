<?php

declare(strict_types=1);

namespace Modules\Events;

use Psr\Container\ContainerInterface;

abstract class Event
{
    /**
     * @return array<string, mixed>
     */
    abstract public function payload(): array;

    /**
     * @param array<string, mixed> $payload
     */
    abstract public static function fromPayload(array $payload, ContainerInterface $container): static;
}
