<?php

declare(strict_types=1);

namespace Modules\Events;

use JsonException;
use Modules\Events\Queue\ListenerJob;
use Modules\Queue\QueueInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

final class Events implements EventsInterface
{
    /** @var array<class-string<Event>, array<class-string, bool>> */
    private array $listeners = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly QueueInterface $queue,
    ) {}

    public function listen(string $eventClass, string $listenerClass, bool $async = false): void
    {
        if (!class_exists($eventClass) || !is_subclass_of($eventClass, Event::class)) {
            throw new EventException("Класс события должен наследоваться от " . Event::class . ": {$eventClass}");
        }
        if (!class_exists($listenerClass)) {
            throw new EventException("Класс listener не найден: {$listenerClass}");
        }

        $listener = new ReflectionClass($listenerClass);
        if (
            !$listener->hasMethod('handle')
            || !$listener->getMethod('handle')->isPublic()
            || $listener->getMethod('handle')->isStatic()
        ) {
            throw new EventException("Listener должен иметь публичный нестатический метод handle(): {$listenerClass}");
        }
        if (isset($this->listeners[$eventClass][$listenerClass])) {
            throw new EventException("Listener уже зарегистрирован для события: {$eventClass} -> {$listenerClass}");
        }

        $this->listeners[$eventClass][$listenerClass] = $async;
    }

    public function dispatch(Event $event): void
    {
        $eventClass = $event::class;
        $listeners = $this->listeners[$eventClass] ?? [];
        $hasAsyncListeners = in_array(true, $listeners, true);
        $payload = $hasAsyncListeners ? $this->jsonPayload($event) : [];

        foreach ($listeners as $listenerClass => $async) {
            if ($async) {
                $this->queue->push(new ListenerJob($this, $eventClass, $listenerClass, $payload));
                continue;
            }

            $this->invoke($listenerClass, $event);
        }
    }

    public function dispatchQueued(string $eventClass, string $listenerClass, array $payload): void
    {
        if (($this->listeners[$eventClass][$listenerClass] ?? false) !== true) {
            throw new EventException("Queued listener не зарегистрирован: {$eventClass} -> {$listenerClass}");
        }

        /** @var class-string<Event> $eventClass */
        $event = $eventClass::fromPayload($payload, $this->container);
        if (!$event instanceof Event || $event::class !== $eventClass) {
            throw new EventException("fromPayload() вернул некорректное событие: {$eventClass}");
        }

        $this->invoke($listenerClass, $event);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Event $event): array
    {
        $payload = $event->payload();
        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new EventException('Payload события должен быть сериализуем в JSON', 0, $exception);
        }

        return $payload;
    }

    /**
     * @param class-string $listenerClass
     */
    private function invoke(string $listenerClass, Event $event): void
    {
        $listener = $this->container->get($listenerClass);
        if (!is_object($listener) || !is_callable([$listener, 'handle'])) {
            throw new EventException("Не удалось разрешить listener: {$listenerClass}");
        }

        $listener->handle($event);
    }
}
