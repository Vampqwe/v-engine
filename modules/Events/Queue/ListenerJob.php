<?php

declare(strict_types=1);

namespace Modules\Events\Queue;

use Modules\Events\EventsInterface;
use Modules\Queue\Job;
use Modules\Queue\QueueException;
use Psr\Container\ContainerInterface;

final class ListenerJob extends Job
{
    /**
     * @param class-string $listenerClass
     * @param array<string, mixed> $eventPayload
     */
    public function __construct(
        private readonly EventsInterface $events,
        private readonly string $eventClass,
        private readonly string $listenerClass,
        private readonly array $eventPayload,
    ) {}

    public function handle(): void
    {
        $this->events->dispatchQueued($this->eventClass, $this->listenerClass, $this->eventPayload);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'event_class'    => $this->eventClass,
            'listener_class' => $this->listenerClass,
            'event_payload'  => $this->eventPayload,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload, ContainerInterface $container): static
    {
        if (
            !isset($payload['event_class'], $payload['listener_class'], $payload['event_payload'])
            || !is_string($payload['event_class'])
            || !is_string($payload['listener_class'])
            || !is_array($payload['event_payload'])
        ) {
            throw new QueueException('Некорректный payload задания event listener');
        }

        $events = $container->get(EventsInterface::class);
        if (!$events instanceof EventsInterface) {
            throw new QueueException('Не удалось разрешить EventsInterface для queued listener');
        }

        return new self(
            $events,
            $payload['event_class'],
            $payload['listener_class'],
            $payload['event_payload'],
        );
    }
}
