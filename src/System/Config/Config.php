<?php

declare(strict_types=1);

namespace VEngine\System\Config;

use Dotenv\Dotenv;
use VEngine\System\Map\MapInterface;

final class Config implements ConfigInterface
{
    private array $loadedConfigs = [];

    public function __construct(
        private readonly string $rootPath,
        private readonly MapInterface $map
    ) {
        if (!isset($_ENV['APP_ENV']) && !isset($_SERVER['APP_ENV']) && class_exists(Dotenv::class)) {
            Dotenv::createImmutable($this->rootPath)->safeLoad();
        }
    }

    public function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public function get(string $file, string $key, mixed $default = null): mixed
    {
        if (!isset($this->loadedConfigs[$file])) {
            $path = $this->rootPath . '/config/' . $file . '.php';
            if (!file_exists($path)) {
                throw new ConfigException("Файл конфигурации не найден: {$path}");
            }
            $this->loadedConfigs[$file] = require $path;
        }
        return $this->map->get($this->loadedConfigs[$file], $key, $default);
    }
}
