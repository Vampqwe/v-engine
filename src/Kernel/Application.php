<?php

declare(strict_types=1);

namespace VEngine\Kernel;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use VEngine\System\Error\ErrorHandlerInterface;
use VEngine\System\Logger\LoggerInterface;

final class Application
{
    private ContainerInterface $container;
    private array $paths = [];
    public function __construct(string $rootPath)
    {
        $root = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->paths = [
            'path.root'   => $root,
            'path.config' => $root . '/config',
            'path.log'    => $root . '/log',
            'path.cache'  => $root . '/storage/cache',
            'path.public' => $root . '/public',
        ];
        $this->buildContainer();
    }
    private function buildContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions($this->paths);
        $builder->addDefinitions($this->paths['path.config'] . '/dependency-rules.php');
        $this->container = $builder->build();
    }
    public function run(): void
    {
        $this->container->get(ErrorHandlerInterface::class)->register();
        $logger = $this->container->get(LoggerInterface::class)->forModule('Kernel');
        $logger->info('Приложение V-engine успешно запущено');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        echo "V-engine запущен. Текущий маршрут: {$uri}";
    }
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
