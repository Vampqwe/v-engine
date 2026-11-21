<?php

declare(strict_types=1);

use Invoker\InvokerInterface;
use Psr\Container\ContainerInterface;
use VEngine\System\Cache\CacheInterface;
use VEngine\System\Cache\CacheFailoverJournal;
use VEngine\System\Cache\FailoverCache;
use VEngine\System\Cache\FileCache;
use VEngine\System\Config\Config;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\DataBase\DataBase;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Error\ErrorHandler;
use VEngine\System\Error\ErrorHandlerInterface;
use VEngine\System\Error\ErrorRenderer;
use VEngine\System\Error\ErrorRendererInterface;
use VEngine\System\File\File;
use VEngine\System\File\FileInterface;
use VEngine\System\Http\Request;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Logger\Logger;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Monitoring\RequestMetrics;
use VEngine\System\Map\Map;
use VEngine\System\Map\MapInterface;
use VEngine\System\Router\Router;
use VEngine\System\Router\RouteCacheFingerprint;
use VEngine\System\Router\RouterInterface;
use VEngine\System\TimeDate\TimeDate;
use VEngine\System\TimeDate\TimeDateInterface;
use VEngine\System\Security\Security;
use VEngine\System\Security\SecurityInterface;
use VEngine\System\Session\Session;
use VEngine\System\Session\SessionInterface;
use VEngine\System\View\View;
use VEngine\System\View\ViewInterface;
use VEngine\System\Validator\Validator;
use VEngine\System\Validator\ValidatorInterface;
use VEngine\System\Cache\RedisCache;
use VEngine\System\Cache\Console\CacheClearCommand;
use VEngine\System\Console\ConsoleApplication;
use VEngine\System\Console\ConsoleApplicationInterface;
use VEngine\System\Redis\RedisClient;
use VEngine\System\RateLimit\RateLimiterInterface;
use VEngine\System\RateLimit\FailoverRateLimiter;
use VEngine\System\RateLimit\FileRateLimiter;
use VEngine\System\RateLimit\RedisRateLimiter;
use VEngine\System\RateLimit\Console\RateLimitGcCommand;
use Modules\Admin\Console\CreateAdminCommand;
use Modules\Admin\Services\AdminUserService;
use Modules\Admin\Services\AdminConsoleService;
use Modules\Admin\Services\EngineStatsService;

return [
    ErrorRendererInterface::class => DI\autowire(ErrorRenderer::class),
    RequestMetrics::class         => DI\autowire(RequestMetrics::class),
    MapInterface::class           => DI\autowire(Map::class),
    FileInterface::class          => DI\autowire(File::class),
    TimeDateInterface::class      => DI\autowire(TimeDate::class),
    DataBaseInterface::class      => DI\autowire(DataBase::class),
    ErrorHandlerInterface::class  => DI\autowire(ErrorHandler::class),
    RequestInterface::class       => DI\autowire(Request::class),
    SessionInterface::class  => DI\autowire(Session::class),
    SecurityInterface::class => DI\autowire(Security::class),
    ValidatorInterface::class => DI\autowire(Validator::class),
    RateLimiterInterface::class => static function (ContainerInterface $c): RateLimiterInterface {
        /** @var ConfigInterface $config */
        $config = $c->get(ConfigInterface::class);
        $fileRateLimiter = new FileRateLimiter(
            $c->get('path.cache') . '/rate-limit',
            $c->get(TimeDateInterface::class),
            $c->get(LoggerInterface::class),
        );

        return match ($config->get('rate-limit', 'driver', 'file')) {
            'file' => $fileRateLimiter,
            'redis' => new FailoverRateLimiter(
                new RedisRateLimiter(
                    $c->get(RedisClient::class),
                    $c->get(RequestMetrics::class),
                ),
                $fileRateLimiter,
                $config->get('rate-limit', 'fallback_hold_seconds', 300),
                $c->get(LoggerInterface::class),
            ),
            default => throw new \LogicException('Неподдерживаемый RATE_LIMIT_DRIVER'),
        };
    },
    RedisClient::class => DI\autowire(RedisClient::class),
    AdminUserService::class => DI\autowire(AdminUserService::class),
    EngineStatsService::class => DI\autowire(EngineStatsService::class)
        ->constructorParameter('siteRoot', DI\get('path.siteRoot'))
        ->constructorParameter('sitesRoot', DI\get('path.sites'))
        ->constructorParameter('modulesRoot', DI\get('path.modules'))
        ->constructorParameter('storageRoot', DI\get('path.storage')),
    AdminConsoleService::class => DI\autowire(AdminConsoleService::class),

    ConsoleApplicationInterface::class => static function (ContainerInterface $c): ConsoleApplicationInterface {
        $console = new ConsoleApplication();
        $console->register($c->get(CreateAdminCommand::class));
        $console->register(new CacheClearCommand($c->get('path.root')));
        $console->register(new RateLimitGcCommand(new FileRateLimiter(
            $c->get('path.cache') . '/rate-limit',
            $c->get(TimeDateInterface::class),
            $c->get(LoggerInterface::class),
        )));
        return $console;
    },

    ConfigInterface::class => static function (ContainerInterface $c): ConfigInterface {
        return new Config(
            $c->get('path.root'),
            $c->get(MapInterface::class),
        );
    },

    LoggerInterface::class => static function (ContainerInterface $c): LoggerInterface {
        return new Logger(
            $c->get('path.log'),
            $c->get(FileInterface::class),
            $c->get(TimeDateInterface::class),
        );
    },

    CacheInterface::class => static function (ContainerInterface $c): CacheInterface {
        $config = $c->get(ConfigInterface::class);
        $driver = $config->get('cache', 'driver', 'file');

        return match ($driver) {
            'redis' => $config->get('cache', 'redis_failover', true)
                ? new FailoverCache(
                    new RedisCache(
                        $c->get(RedisClient::class),
                        $c->get(RequestMetrics::class),
                        $c->get(TimeDateInterface::class),
                    ),
                    new FileCache(
                        $c->get('path.cache') . '/redis-fallback/data',
                        $c->get(FileInterface::class),
                        $c->get(TimeDateInterface::class),
                    ),
                    new CacheFailoverJournal(
                        $c->get('path.cache') . '/redis-fallback/pending.dat',
                        $c->get(FileInterface::class),
                    ),
                    $c->get(TimeDateInterface::class),
                    $c->get(LoggerInterface::class),
                )
                : new RedisCache(
                    $c->get(RedisClient::class),
                    $c->get(RequestMetrics::class),
                    $c->get(TimeDateInterface::class),
                ),
            'file' => new FileCache(
                $c->get('path.cache'),
                $c->get(FileInterface::class),
                $c->get(TimeDateInterface::class),
            ),
            default => throw new \LogicException("Неподдерживаемый CACHE_DRIVER: {$driver}"),
        };
    },

    RouterInterface::class => static function (ContainerInterface $c): RouterInterface {
        $router = new Router($c, $c->get(InvokerInterface::class));

        /** @var ConfigInterface $config */
        $config = $c->get(ConfigInterface::class);
        $isProd = ($config->env('APP_ENV', 'prod') === 'prod');
        $cacheFile = $c->get('path.cache') . '/routes.compiled.php';
        $routeFiles = glob($c->get('path.routes') . '/*.php') ?: [];
        $moduleRouteFiles = glob($c->get('path.modules') . '/*/routes.php') ?: [];
        $allRouteFiles = array_merge($routeFiles, $moduleRouteFiles);
        $routeFingerprint = $isProd
            ? RouteCacheFingerprint::fromFiles($allRouteFiles)
            : '';

        if ($isProd && is_file($cacheFile)) {
            $compiled = require $cacheFile;
            if (
                is_array($compiled)
                && ($compiled['fingerprint'] ?? null) === $routeFingerprint
                && isset($compiled['routes'], $compiled['named'])
            ) {
                $router->loadCompiled([
                    'routes' => $compiled['routes'],
                    'named'  => $compiled['named'],
                ]);
                return $router;
            }
        }

        // 1. Загружаем маршруты выбранного сайта
        sort($routeFiles);
        foreach ($routeFiles as $file) {
            $registrar = require $file;
            if (is_callable($registrar)) {
                $registrar($router);
            }
        }

        // 2. Автоматически подключаем маршруты изолированных модулей (modules/*/routes.php)
        sort($moduleRouteFiles);
        foreach ($moduleRouteFiles as $file) {
            $registrar = require $file;
            if (is_callable($registrar)) {
                $registrar($router);
            }
        }

        // 3. Сохраняем скомпилированные маршруты в prod-режиме
        if ($isProd) {
            $exported = $router->exportCompiled();
            if ($exported !== null) {
                $cacheDir = dirname($cacheFile);
                $tmpFile = $cacheFile . '.' . uniqid('', true) . '.tmp';
                $cachedRoutes = [
                    'fingerprint' => $routeFingerprint,
                    'routes'      => $exported['routes'],
                    'named'       => $exported['named'],
                ];
                $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($cachedRoutes, true) . ";\n";
                try {
                    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0o755, true) && !is_dir($cacheDir)) {
                        throw new \RuntimeException("Не удалось создать каталог кэша маршрутов: {$cacheDir}");
                    }
                    if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
                        throw new \RuntimeException("Не удалось записать временный кэш маршрутов: {$tmpFile}");
                    }
                    if (!rename($tmpFile, $cacheFile)) {
                        throw new \RuntimeException("Не удалось заменить кэш маршрутов: {$cacheFile}");
                    }
                } catch (\Throwable $e) {
                    if (is_file($tmpFile)) {
                        unlink($tmpFile);
                    }
                    $c->get(LoggerInterface::class)
                        ->forModule('Router')
                        ->error('Не удалось сохранить скомпилированный кэш маршрутов', [
                            'exception' => $e::class,
                            'message'   => $e->getMessage(),
                        ]);
                }
            }
        }

        return $router;
    },


    ViewInterface::class => static function (ContainerInterface $c): ViewInterface {
        return new View(
            $c->get('path.views'),
            $c->get('path.modules'),
            $c->get(SecurityInterface::class),
            $c->get(RouterInterface::class),
            $c->get(SessionInterface::class),
        );
    },
];
