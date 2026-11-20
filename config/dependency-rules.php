<?php

declare(strict_types=1);

use Invoker\InvokerInterface;
use Psr\Container\ContainerInterface;
use VEngine\System\Cache\CacheInterface;
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
use VEngine\System\Map\Map;
use VEngine\System\Map\MapInterface;
use VEngine\System\Router\Router;
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
use VEngine\System\Redis\RedisClient;
use VEngine\System\RateLimit\RateLimiterInterface;
use VEngine\System\RateLimit\RedisRateLimiter;
use Modules\Admin\Services\AdminUserService;

return [
    ErrorRendererInterface::class => DI\autowire(ErrorRenderer::class),
    MapInterface::class           => DI\autowire(Map::class),
    FileInterface::class          => DI\autowire(File::class),
    TimeDateInterface::class      => DI\autowire(TimeDate::class),
    DataBaseInterface::class      => DI\autowire(DataBase::class),
    ErrorHandlerInterface::class  => DI\autowire(ErrorHandler::class),
    RequestInterface::class       => DI\autowire(Request::class),
    SessionInterface::class  => DI\autowire(Session::class),
    SecurityInterface::class => DI\autowire(Security::class),
    ValidatorInterface::class => DI\autowire(Validator::class),
    RateLimiterInterface::class => DI\autowire(RedisRateLimiter::class),
    RedisClient::class => DI\autowire(RedisClient::class),
    AdminUserService::class => DI\autowire(AdminUserService::class),


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
        /** @var ConfigInterface $config */
        $config = $c->get(ConfigInterface::class);
        $driver = (string) $config->env('CACHE_DRIVER', 'file');

        if ($driver === 'redis') {
            return new RedisCache(
                $c->get(RedisClient::class),
                $c->get(TimeDateInterface::class),
            );
        }

        return new FileCache(
            $c->get('path.cache'),
            $c->get(FileInterface::class),
            $c->get(TimeDateInterface::class),
        );
    },

    RouterInterface::class => static function (ContainerInterface $c): RouterInterface {
        $router = new Router($c, $c->get(InvokerInterface::class));

        /** @var ConfigInterface $config */
        $config = $c->get(ConfigInterface::class);
        $isProd = ($config->env('APP_ENV', 'dev') === 'prod');
        $cacheFile = $c->get('path.cache') . '/routes.compiled.php';

        if ($isProd && is_file($cacheFile)) {
            $compiled = require $cacheFile;
            if (is_array($compiled) && isset($compiled['routes'], $compiled['named'])) {
                $router->loadCompiled($compiled);
                return $router;
            }
        }

        // 1. Загружаем основные маршруты из config/routes/*.php
        $routesDir = $c->get('path.routes');
        if (is_dir($routesDir)) {
            $files = glob($routesDir . '/*.php') ?: [];
            sort($files);
            foreach ($files as $file) {
                $registrar = require $file;
                if (is_callable($registrar)) {
                    $registrar($router);
                }
            }
        }

        // 2. Автоматически подключаем маршруты изолированных модулей (modules/*/routes.php)
        $modulesDir = $c->get('path.modules');
        if (is_dir($modulesDir)) {
            $moduleRoutes = glob($modulesDir . '/*/routes.php') ?: [];
            sort($moduleRoutes);
            foreach ($moduleRoutes as $file) {
                $registrar = require $file;
                if (is_callable($registrar)) {
                    $registrar($router);
                }
            }
        }

        // 3. Сохраняем скомпилированные маршруты в prod-режиме
        if ($isProd) {
            $exported = $router->exportCompiled();
            if ($exported !== null) {
                $cacheDir = dirname($cacheFile);
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0o755, true);
                }
                $tmpFile = $cacheFile . '.' . uniqid('', true) . '.tmp';
                $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($exported, true) . ";\n";
                if (@file_put_contents($tmpFile, $content, LOCK_EX) !== false) {
                    @rename($tmpFile, $cacheFile);
                }
            }
        }

        return $router;
    },


    ViewInterface::class => static function (ContainerInterface $c): ViewInterface {
        return new View(
            $c->get('path.views'),
            $c->get(SecurityInterface::class),
            $c->get(RouterInterface::class),
            $c->get(SessionInterface::class),
        );
    },
];
