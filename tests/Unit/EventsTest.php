<?php

declare(strict_types=1);

namespace Tests\Unit;

use DI\Container;
use Modules\Events\Event;
use Modules\Events\EventException;
use Modules\Events\Events;
use Modules\Events\EventsInterface;
use Modules\Events\ModuleEventRegistrar;
use Modules\Events\Queue\ListenerJob;
use Modules\Queue\Job;
use Modules\Queue\QueueInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class EventsTest extends TestCase
{
    private string $modulesPath;
    private Container $container;
    private EventTestListener $listener;
    private Events $events;
    private QueueInterface $queue;

    protected function setUp(): void
    {
        EventTestListener::$handled = [];
        $this->modulesPath = sys_get_temp_dir() . '/vengine-event-modules-' . bin2hex(random_bytes(6));
        $this->container = new Container();
        $this->listener = new EventTestListener();
        $this->container->set(EventTestListener::class, $this->listener);
        $this->queue = $this->createMock(QueueInterface::class);
        $this->events = new Events($this->container, $this->queue);
        $this->container->set(EventsInterface::class, $this->events);
    }

    protected function tearDown(): void
    {
        $moduleFile = $this->modulesPath . '/Sample/module.php';
        if (is_file($moduleFile)) {
            unlink($moduleFile);
        }
        if (is_dir(dirname($moduleFile))) {
            rmdir(dirname($moduleFile));
        }
        if (is_dir($this->modulesPath)) {
            rmdir($this->modulesPath);
        }
    }

    public function testDispatchInvokesSynchronousListenersImmediately(): void
    {
        $this->queue->expects(self::never())->method('push');
        $this->events->listen(EventTestEvent::class, EventTestListener::class);

        $this->events->dispatch(new EventTestEvent('created'));

        self::assertSame(['created'], EventTestListener::$handled);
    }

    public function testAsyncListenerIsQueuedAndRestoredFromJsonPayload(): void
    {
        $queuedJob = null;
        $this->queue->expects(self::once())
            ->method('push')
            ->willReturnCallback(static function (Job $job) use (&$queuedJob): string {
                $queuedJob = $job;
                return 'queued-id';
            });
        $this->events->listen(EventTestEvent::class, EventTestListener::class, async: true);

        $this->events->dispatch(new EventTestEvent('queued'));

        self::assertInstanceOf(ListenerJob::class, $queuedJob);
        self::assertSame([], EventTestListener::$handled);
        self::assertStringNotContainsString('O:', json_encode($queuedJob->payload(), JSON_THROW_ON_ERROR));

        $restoredJob = ListenerJob::fromPayload($queuedJob->payload(), $this->container);
        $restoredJob->handle();

        self::assertSame(['queued'], EventTestListener::$handled);
    }

    public function testQueuedDispatchRejectsUnregisteredListener(): void
    {
        $this->expectException(EventException::class);

        $this->events->dispatchQueued(EventTestEvent::class, EventTestListener::class, ['name' => 'forged']);
    }

    public function testModulePhpRegistersListenersWithTheSharedEventBus(): void
    {
        $moduleDirectory = $this->modulesPath . '/Sample';
        mkdir($moduleDirectory, 0o700, true);
        $registrar = "<?php\nreturn static function (\\Modules\\Events\\EventsInterface \$events): void {\n"
            . '$events->listen(' . var_export(EventTestEvent::class, true) . ', '
            . var_export(EventTestListener::class, true) . ");\n};\n";
        file_put_contents($moduleDirectory . '/module.php', $registrar);

        (new ModuleEventRegistrar())->register($this->modulesPath, $this->events);
        $this->events->dispatch(new EventTestEvent('module'));

        self::assertSame(['module'], EventTestListener::$handled);
    }
}

final class EventTestEvent extends Event
{
    public function __construct(public readonly string $name) {}

    public function payload(): array
    {
        return ['name' => $this->name];
    }

    public static function fromPayload(array $payload, ContainerInterface $container): static
    {
        if (!isset($payload['name']) || !is_string($payload['name'])) {
            throw new EventException('Event test payload is invalid');
        }

        return new self($payload['name']);
    }
}

final class EventTestListener
{
    /** @var list<string> */
    public static array $handled = [];

    public function handle(EventTestEvent $event): void
    {
        self::$handled[] = $event->name;
    }
}
