$ErrorActionPreference = "Stop"
$RootDir = "V-engine"

# Вспомогательная функция для создания директорий и записи файлов в UTF-8 без BOM
function New-ProjectFile {
    param (
        [string]$RelativePath,
        [string]$Content
    )
    $FullPath = Join-Path -Path $RootDir -ChildPath $RelativePath
    $Dir = Split-Path -Path $FullPath -Parent
    if (-not (Test-Path -Path $Dir)) {
        New-Item -ItemType Directory -Path $Dir -Force | Out-Null
    }
    $Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) $FullPath), $Content.TrimStart(), $Utf8NoBom)
    Write-Host "Создан: $FullPath" -ForegroundColor Green
}

Write-Host "Развертывание структуры проекта V-engine..." -ForegroundColor Cyan

# Создаем пустые рабочие директории с .gitkeep
$EmptyDirs = @("log", "storage/cache")
foreach ($Dir in $EmptyDirs) {
    New-ProjectFile -RelativePath "$Dir/.gitkeep" -Content ""
}

# 1. Корневые файлы: composer.json, config.env, .gitignore
New-ProjectFile -RelativePath "composer.json" -Content @'
{
    "name": "v-engine/core",
    "require": {
        "php": ">=8.2",
        "ext-pdo": "*",
        "php-di/php-di": "^7.0",
        "vlucas/phpdotenv": "^5.6"
    },
    "autoload": {
        "psr-4": {
            "VEngine\\": "src/"
        }
    }
}
'@

New-ProjectFile -RelativePath "config.env" -Content @'
APP_ENV=dev
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=v_engine
DB_USER=root
DB_PASS=secret
'@

New-ProjectFile -RelativePath ".gitignore" -Content @'
/vendor/
/log/*.log
/storage/cache/*.cache
config.env
'@

# 2. Конфигурации: config/
New-ProjectFile -RelativePath "config/database.php" -Content @'
<?php

declare(strict_types=1);

return [
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
'@

New-ProjectFile -RelativePath "config/dependency-rules.php" -Content @'
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

return [
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
'@

# 3. Модуль Exception
New-ProjectFile -RelativePath "src/System/Exception/BaseException.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\Exception;

use Exception;

class BaseException extends Exception
{
}
'@

# Генерация именованных исключений для модулей
$ModulesWithExceptions = @("Map", "Cache", "File", "Config", "DataBase", "Error", "Logger", "TimeDate")
foreach ($Mod in $ModulesWithExceptions) {
    $ExCode = @"
<?php

declare(strict_types=1);

namespace VEngine\System\$Mod;

use VEngine\System\Exception\BaseException;

class ${Mod}Exception extends BaseException
{
}
"@
    New-ProjectFile -RelativePath "src/System/$Mod/${Mod}Exception.php" -Content $ExCode
}

# 4. Модуль TimeDate
New-ProjectFile -RelativePath "src/System/TimeDate/TimeDateInterface.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\TimeDate;

use DateTimeImmutable;

interface TimeDateInterface
{
    public function now(string $format = 'Y-m-d H:i:s'): string;
    public function timestamp(): int;
    public function immutable(?string $time = 'now'): DateTimeImmutable;
}
'@

New-ProjectFile -RelativePath "src/System/TimeDate/TimeDate.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\TimeDate;

use DateTimeImmutable;

final class TimeDate implements TimeDateInterface
{
    public function now(string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTimeImmutable())->format($format);
    }

    public function timestamp(): int
    {
        return time();
    }

    public function immutable(?string $time = 'now'): DateTimeImmutable
    {
        return new DateTimeImmutable($time ?? 'now');
    }
}
'@

# 5. Модуль Map
New-ProjectFile -RelativePath "src/System/Map/MapInterface.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\Map;

interface MapInterface
{
    public function get(array $array, string $key, mixed $default = null): mixed;
    public function has(array $array, string $key): bool;
    public function set(array &$array, string $key, mixed $value): void;
}
'@

New-ProjectFile -RelativePath "src/System/Map/Map.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\Map;

final class Map implements MapInterface
{
    public function get(array $array, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    public function has(array $array, string $key): bool
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }
        return true;
    }

    public function set(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
        $array[array_shift($keys)] = $value;
    }
}
'@

# 6. Модуль File
New-ProjectFile -RelativePath "src/System/File/FileInterface.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\File;

interface FileInterface
{
    public function exists(string $path): bool;
    public function read(string $path): string;
    public function write(string $path, string $content, bool $append = false): bool;
    public function ensureDirectory(string $dirPath): void;
}
'@

New-ProjectFile -RelativePath "src/System/File/File.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\File;

final class File implements FileInterface
{
    public function exists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }

    public function read(string $path): string
    {
        if (!$this->exists($path)) {
            throw new FileException("Файл не найден: {$path}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new FileException("Ошибка чтения файла: {$path}");
        }
        return $content;
    }

    public function write(string $path, string $content, bool $append = false): bool
    {
        $this->ensureDirectory(dirname($path));
        $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
        if (file_put_contents($path, $content, $flags) === false) {
            throw new FileException("Ошибка записи в файл: {$path}");
        }
        return true;
    }

    public function ensureDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath) && !mkdir($dirPath, 0755, true) && !is_dir($dirPath)) {
            throw new FileException("Не удалось создать директорию: {$dirPath}");
        }
    }
}
'@

# 7. Модуль Config
New-ProjectFile -RelativePath "src/System/Config/ConfigInterface.php" -Content @'
<?php

declare(strict_types=1);

namespace VEngine\System\Config;

interface ConfigInterface
{
    public function env(string $key, mixed $default = null): mixed;
    public function get(string $file, string $key, mixed $default = null): mixed;
}
'@

New-ProjectFile -RelativePath "src/System/Config/Config.php" -Content @'
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
        $dotenv = Dotenv::createImmutable($this->rootPath, 'config.env');
        $dotenv->safeLoad();
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
'@

New-ProjectFile -RelativePath "src/System/Logger/LoggerInterface.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Logger;
interface LoggerInterface
{
public function forModule(string $moduleName): self;
public function info(string $message, array $context = []): void;
public function error(string $message, array $context = []): void;
}
'@
New-ProjectFile -RelativePath "src/System/Logger/Logger.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Logger;
use VEngine\System\File\FileInterface;
use VEngine\System\TimeDate\TimeDateInterface;
final class Logger implements LoggerInterface
{
private string $moduleName = 'system';
public function __construct(
private readonly string $logDir,
private readonly FileInterface $file,
private readonly TimeDateInterface $timeDate
) {
}
public function forModule(string $moduleName): self
{
$clone = clone $this;
$clone->moduleName = preg_replace('/[^a-zA-Z0-9_-]/', '', $moduleName);
return $clone;
}
public function info(string $message, array $context = []): void
{
$this->writeLog('INFO', $message, $context);
}
public function error(string $message, array $context = []): void
{
$this->writeLog('ERROR', $message, $context);
}
private function writeLog(string $level, string $message, array $context): void
{
$date = $this->timeDate->now();
$contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
$line = "[{$date}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
$path = "{$this->logDir}/{$this->moduleName}.log";
$this->file->write($path, $line, true);
}
}
'@

New-ProjectFile -RelativePath "src/System/Cache/CacheInterface.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Cache;
interface CacheInterface
{
public function get(string $key, mixed $default = null): mixed;
public function set(string $key, mixed $value, int $ttl = 3600): bool;
public function delete(string $key): bool;
}
'@
New-ProjectFile -RelativePath "src/System/Cache/AbstractCache.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Cache;
use VEngine\System\TimeDate\TimeDateInterface;
abstract class AbstractCache implements CacheInterface
{
public function __construct(
protected readonly TimeDateInterface $timeDate
) {
}
protected function preparePayload(mixed $value, int $ttl): array
{
return [
'expires_at' => $this->timeDate->timestamp() + $ttl,
'data' => $value,
];
}
protected function isExpired(array $payload): bool
{
return !isset($payload['expires_at']) || $this->timeDate->timestamp() > $payload['expires_at'];
}
}
'@
New-ProjectFile -RelativePath "src/System/Cache/FileCache.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Cache;
use VEngine\System\File\FileInterface;
use VEngine\System\TimeDate\TimeDateInterface;
final class FileCache extends AbstractCache
{
public function __construct(
private readonly string $cacheDir,
private readonly FileInterface $file,
TimeDateInterface $timeDate
) {
parent::__construct($timeDate);
}
public function get(string $key, mixed $default = null): mixed
{
$path = $this->getPath($key);
if (!$this->file->exists($path)) {
return $default;
}
$payload = unserialize($this->file->read($path));
if (!is_array($payload) || $this->isExpired($payload)) {
$this->delete($key);
return $default;
}
return $payload['data'];
}
public function set(string $key, mixed $value, int $ttl = 3600): bool
{
$payload = serialize($this->preparePayload($value, $ttl));
return $this->file->write($this->getPath($key), $payload);
}
public function delete(string $key): bool
{
$path = $this->getPath($key);
return $this->file->exists($path) ? unlink($path) : true;
}
private function getPath(string $key): string
{
return $this->cacheDir . '/' . md5($key) . '.cache';
}
}
'@

New-ProjectFile -RelativePath "src/System/DataBase/DataBaseInterface.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\DataBase;
use PDO;
use PDOStatement;
interface DataBaseInterface
{
public function connection(): PDO;
public function query(string $sql, array $params = []): PDOStatement;
}
'@
New-ProjectFile -RelativePath "src/System/DataBase/DataBase.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\DataBase;
use PDO;
use PDOException;
use PDOStatement;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Logger\LoggerInterface;
final class DataBase implements DataBaseInterface
{
private ?PDO $pdo = null;
private LoggerInterface $logger;
public function __construct(
private readonly ConfigInterface $config,
LoggerInterface $logger
) {
$this->logger = $logger->forModule('DataBase');
}
public function connection(): PDO
{
if ($this->pdo === null) {
$host = $this->config->env('DB_HOST', '127.0.0.1');
$port = $this->config->env('DB_PORT', '3306');
$name = $this->config->env('DB_NAME', 'v_engine');
$user = $this->config->env('DB_USER', 'root');
$pass = $this->config->env('DB_PASS', '');
$charset = $this->config->get('database', 'charset', 'utf8mb4');
$dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
try {
$this->pdo = new PDO($dsn, $user, $pass, [
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES => false,
]);
} catch (PDOException $e) {
$this->logger->error('Ошибка подключения к MySQL', ['error' => $e->getMessage()]);
throw new DataBaseException('Не удалось подключиться к базе данных', 0, $e);
}
}
return $this->pdo;
}
public function query(string $sql, array $params = []): PDOStatement
{
try {
$stmt = $this->connection()->prepare($sql);
$stmt->execute($params);
return $stmt;
} catch (PDOException $e) {
$this->logger->error('Ошибка выполнения SQL', ['sql' => $sql, 'error' => $e->getMessage()]);
throw new DataBaseException('Ошибка выполнения запроса к БД', 0, $e);
}
}
}
'@

New-ProjectFile -RelativePath "src/System/Error/ErrorHandlerInterface.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Error;
interface ErrorHandlerInterface
{
public function register(): void;
}
'@
New-ProjectFile -RelativePath "src/System/Error/ErrorHandler.php" -Content @'
<?php
declare(strict_types=1);
namespace VEngine\System\Error;
use ErrorException;
use Throwable;
use VEngine\System\Logger\LoggerInterface;
final class ErrorHandler implements ErrorHandlerInterface
{
private LoggerInterface $logger;
public function __construct(LoggerInterface $logger)
{
$this->logger = $logger->forModule('Error');
}
public function register(): void
{
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
if (!(error_reporting() & $severity)) {
return false;
}
throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function (Throwable $e): void {
$this->logger->error($e->getMessage(), [
'exception' => $e::class,
'file' => $e->getFile(),
'line' => $e->getLine(),
]);
http_response_code(500);
echo 'Internal Server Error';
});
}
}
'@

New-ProjectFile -RelativePath "src/Kernel/Application.php" -Content @'
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
'@
New-ProjectFile -RelativePath "public/index.php" -Content @'
<?php
declare(strict_types=1);
use VEngine\Kernel\Application;
define('BASE_PATH', dirname(DIR));
require BASE_PATH . '/vendor/autoload.php';
$app = new Application(BASE_PATH);
$app->run();
'@
Write-Host "`nГотово! Структура V-engine создана." -ForegroundColor Cyan
Write-Host "Выполните следующие команды для запуска:" -ForegroundColor Yellow
Write-Host "  cd V-engine"
Write-Host "  composer install"
Write-Host "  php -S localhost:8000 -t public"