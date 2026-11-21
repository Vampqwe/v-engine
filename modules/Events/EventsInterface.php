<?php

declare(strict_types=1);

namespace Modules\Events;

interface EventsInterface
{
    /**
     * @param class-string<Event> $eventClass
     * @param class-string $listenerClass
     */
    public function listen(string $eventClass, string $listenerClass, bool $async = false): void;

    public function dispatch(Event $event): void;

    /**
     * @param class-string<Event> $eventClass
     * @param class-string $listenerClass
     * @param array<string, mixed> $payload
     */
    public function dispatchQueued(string $eventClass, string $listenerClass, array $payload): void;
}
