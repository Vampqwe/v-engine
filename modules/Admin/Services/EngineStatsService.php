<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use VEngine\System\Config\ConfigInterface;
use VEngine\System\DataBase\DataBaseException;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Redis\RedisClient;
use VEngine\System\Redis\RedisException;

final class EngineStatsService
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly DataBaseInterface $database,
        private readonly RedisClient $redis,
        private readonly string $siteRoot,
        private readonly string $sitesRoot,
        private readonly string $modulesRoot,
        private readonly string $storageRoot,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $cacheDriver = (string) $this->config->get('cache', 'driver', 'file');
        $sessionDriver = (string) $this->config->get('session', 'driver', 'file');
        $rateLimitDriver = (string) $this->config->get('rate-limit', 'driver', 'file');
        $redisDrivers = array_values(array_filter([
            $cacheDriver === 'redis' ? 'cache' : null,
            $sessionDriver === 'redis' ? 'sessions' : null,
            $rateLimitDriver === 'redis' ? 'rate limiter' : null,
        ]));
        $redisStatus = $this->redisStatus($redisDrivers);
        $modules = $this->moduleNames();

        return [
            'application' => [
                'name'        => (string) $this->config->get('app', 'name', 'V-engine'),
                'environment' => (string) $this->config->env('APP_ENV', 'prod'),
                'site'        => basename($this->siteRoot),
                'site_count'  => $this->directoryCount($this->sitesRoot),
            ],
            'runtime' => [
                'php_version'   => PHP_VERSION,
                'sapi'          => PHP_SAPI,
                'server_time'   => date('d.m.Y H:i:s T'),
                'memory_used'   => $this->formatBytes(memory_get_usage(true)),
                'memory_peak'   => $this->formatBytes(memory_get_peak_usage(true)),
                'memory_limit'  => $this->formatIniBytes((string) ini_get('memory_limit')),
                'php_extensions' => [
                    'pdo'       => extension_loaded('pdo'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'mbstring'  => extension_loaded('mbstring'),
                    'redis'     => extension_loaded('redis'),
                ],
            ],
            'structure' => [
                'route_files'  => $this->phpFileCount($this->siteRoot . '/routes'),
                'modules'      => $modules,
                'module_count' => count($modules),
            ],
            'storage' => $this->storageStats(),
            'database' => $this->databaseStats(),
            'cache' => [
                'driver'       => $cacheDriver,
                'redis_status' => $redisStatus,
                'redis_usage'  => $redisDrivers,
                'file_status'  => $this->isWritableDirectory($this->storageRoot . '/cache/sites/' . basename($this->siteRoot)),
                'failover'     => (bool) $this->config->get('cache', 'redis_failover', false),
            ],
            'session' => [
                'driver' => $sessionDriver,
                'status' => $sessionDriver === 'redis' ? $redisStatus : 'configured',
            ],
        ];
    }

    private function databaseStats(): array
    {
        try {
            $version = $this->database->query('SELECT VERSION()')->fetchColumn();
        } catch (DataBaseException) {
            return [
                'status'      => 'unavailable',
                'version'     => null,
                'admin_users' => null,
            ];
        }

        try {
            $adminUsers = $this->database->query('SELECT COUNT(*) FROM `admin_users`')->fetchColumn();
        } catch (DataBaseException) {
            $adminUsers = null;
        }

        return [
            'status'      => 'connected',
            'version'     => is_string($version) ? $version : null,
            'admin_users' => is_numeric($adminUsers) ? (int) $adminUsers : null,
        ];
    }

    /**
     * @param list<string> $redisDrivers
     */
    private function redisStatus(array $redisDrivers): string
    {
        if ($redisDrivers === []) {
            return 'not_used';
        }

        try {
            return $this->redis->connection()->ping() !== false ? 'connected' : 'unavailable';
        } catch (RedisException|\RedisException) {
            return 'unavailable';
        }
    }

    private function storageStats(): array
    {
        $free = @disk_free_space($this->storageRoot);
        $total = @disk_total_space($this->storageRoot);

        if (
            (!is_float($free) && !is_int($free))
            || (!is_float($total) && !is_int($total))
            || $total <= 0
        ) {
            return [
                'available' => false,
                'free'      => null,
                'total'     => null,
                'used_percent' => null,
            ];
        }

        return [
            'available'    => true,
            'free'         => $this->formatBytes((int) $free),
            'total'        => $this->formatBytes((int) $total),
            'used_percent' => (int) round((($total - $free) / $total) * 100),
        ];
    }

    /**
     * @return list<string>
     */
    private function moduleNames(): array
    {
        $modules = [];
        foreach (glob($this->modulesRoot . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            if (is_file($path . '/routes.php')) {
                $modules[] = basename($path);
            }
        }

        sort($modules);
        return $modules;
    }

    private function directoryCount(string $path): int
    {
        return count(glob($path . '/*', GLOB_ONLYDIR) ?: []);
    }

    private function phpFileCount(string $path): int
    {
        return count(glob($path . '/*.php') ?: []);
    }

    private function isWritableDirectory(string $path): bool
    {
        while (!is_dir($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        return is_dir($path) && is_writable($path);
    }

    private function formatIniBytes(string $value): string
    {
        if ($value === '' || $value === '-1') {
            return 'Без лимита';
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        $bytes = match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };

        return $this->formatBytes((int) $bytes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $size = max(0, $bytes);
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unit];
    }
}
