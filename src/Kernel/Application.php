<?php

declare(strict_types=1);

namespace VEngine\Kernel;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use VEngine\System\Error\ErrorHandlerInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Router\RouterInterface;

final class Application
{
    private ContainerInterface $container;
    private array $paths = [];

    public function __construct(string $rootPath)
    {
        $root = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->paths = [
            'path.root'   => $root,
			'path.app' => $root . '/app',
            'path.config' => $root . '/config',
            'path.routes' => $root . '/config/routes',
            'path.log'    => $root . '/log',
            'path.cache'  => $root . '/storage/cache',
            'path.public' => $root . '/public',
			'path.views' => $root . '/views'
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
        $request = $this->container->get(RequestInterface::class);

        $logger->info('Обработка запроса', [
            'method' => $request->method(),
            'uri'    => $request->uri(),
        ]);

        $router = $this->container->get(RouterInterface::class);
        $response = $router->dispatch($request);
        $response->send();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
