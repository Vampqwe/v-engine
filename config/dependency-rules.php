<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use VEngine\System\Cache\CacheInterface;
use VEngine\System\Cache\FileCache;
use VEngine\System\Config\Config;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\DataBase\DataBase;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Error\ErrorHandler;
use VEngine\System\Error\ErrorHandlerInterface;
use VEngine\System\File\File;
use VEngine\System\File\FileInterface;
use VEngine\System\Logger\Logger;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Map\Map;
use VEngine\System\Map\MapInterface;
use VEngine\System\TimeDate\TimeDate;
use VEngine\System\TimeDate\TimeDateInterface;
use VEngine\System\Error\ErrorRenderer;
use VEngine\System\Error\ErrorRendererInterface;

return [
	ErrorRendererInterface::class => DI\autowire(ErrorRenderer::class),
    MapInterface::class => DI\autowire(Map::class),
    FileInterface::class => DI\autowire(File::class),
    TimeDateInterface::class => DI\autowire(TimeDate::class),
    DataBaseInterface::class => DI\autowire(DataBase::class),
    ErrorHandlerInterface::class => DI\autowire(ErrorHandler::class),

    ConfigInterface::class => static function (ContainerInterface $c): ConfigInterface {
        return new Config(
            $c->get('path.root'),
            $c->get(MapInterface::class)
        );
    },

    LoggerInterface::class => static function (ContainerInterface $c): LoggerInterface {
        return new Logger(
            $c->get('path.log'),
            $c->get(FileInterface::class),
            $c->get(TimeDateInterface::class)
        );
    },

    CacheInterface::class => static function (ContainerInterface $c): CacheInterface {
        return new FileCache(
            $c->get('path.cache'),
            $c->get(FileInterface::class),
            $c->get(TimeDateInterface::class)
        );
    },
];
