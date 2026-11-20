<?php

declare(strict_types=1);

namespace VEngine\Kernel;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use VEngine\System\Error\ErrorHandlerInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Monitoring\RequestMetrics;
use VEngine\System\Router\RouterInterface;

final class Application
{
    private ContainerInterface $container;
    private array $paths = [];
    private bool $isProd;
    private int $requestStartedAt;

    public function __construct(string $rootPath)
    {
        $this->requestStartedAt = hrtime(true);
        $root = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $siteName = $_ENV['APP_SITE'] ?? $_SERVER['APP_SITE'] ?? 'Corporate';
        if (!is_string($siteName) || preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\z/', $siteName) !== 1) {
            throw new \InvalidArgumentException('APP_SITE должен быть корректным именем сайта');
        }

        $siteRoot = $root . '/sites/' . $siteName;
        if (!is_dir($siteRoot)) {
            throw new \InvalidArgumentException("Сайт '{$siteName}' не найден в {$siteRoot}");
        }

        foreach (['App', 'routes', 'views', 'public'] as $directory) {
            if (!is_dir($siteRoot . '/' . $directory)) {
                throw new \InvalidArgumentException(
                    "В сайте '{$siteName}' отсутствует обязательная директория: {$directory}",
                );
            }
        }

        $this->paths = [
            'path.root'     => $root,
            'path.app'      => $root . '/app',
            'path.modules'  => $root . '/modules',
            'path.site'     => $siteName,
            'path.siteRoot' => $siteRoot,
            'path.siteApp'  => $siteRoot . '/App',
            'path.config'   => $root . '/config',
            'path.sites'    => $root . '/sites',
            'path.storage'  => $root . '/storage',
            'path.routes'   => $siteRoot . '/routes',
            'path.log'      => $root . '/log',
            'path.cache'    => $root . '/storage/cache/sites/' . $siteName,
            'path.public'   => $siteRoot . '/public',
            'path.views'    => $siteRoot . '/views',
        ];

        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev';
        $this->isProd = ($env === 'prod');

        $this->buildContainer();
    }

    private function buildContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        if ($this->isProd) {
            $diCacheDir = $this->paths['path.cache'] . '/di';
            if (!is_dir($diCacheDir)) {
                @mkdir($diCacheDir, 0o755, true);
            }
            $builder->enableCompilation($diCacheDir);
            $builder->writeProxiesToFile(true, $diCacheDir . '/proxies');
        }

        $builder->addDefinitions($this->paths);
        $builder->addDefinitions($this->paths['path.config'] . '/dependency-rules.php');
        $this->container = $builder->build();
    }

    public function run(): void
    {
        $this->container->get(ErrorHandlerInterface::class)->register();

        $request = $this->container->get(RequestInterface::class);

        if (!$this->isProd) {
            $logger = $this->container->get(LoggerInterface::class)->forModule('Kernel');
            $logger->info('Обработка запроса', [
                'method' => $request->method(),
                'uri'    => $request->uri(),
            ]);
        }

        $metrics = $this->container->get(RequestMetrics::class);
        $metrics->startRequest($this->requestStartedAt);
        $router = $this->container->get(RouterInterface::class);
        $response = $router->dispatch($request);
        $response->withHeader('Server-Timing', $metrics->serverTiming())->send();
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
