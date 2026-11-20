<?php

declare(strict_types=1);

namespace VEngine\System\Session;

use VEngine\System\Config\ConfigInterface;
use VEngine\System\Map\MapInterface;
use VEngine\System\Redis\RedisNamespace;

final class Session implements SessionInterface
{
    private const FLASH_KEY = '_vengine_flash';

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly MapInterface $map,
    ) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new SessionException('Невозможно запустить сессию: HTTP-заголовки уже отправлены');
        }

        $lifetime = (int) $this->config->get('session', 'lifetime', 7200);
        $driver = (string) $this->config->get('session', 'driver', $this->config->env('SESSION_DRIVER', 'file'));

        if (!in_array($driver, ['file', 'redis'], true)) {
            throw new SessionException("Неподдерживаемый драйвер сессий: {$driver}");
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        if ($driver === 'redis') {
            $this->configureRedisHandler();
        }

        $name = (string) $this->config->get('session', 'name', 'VENGINE_SESSID');
        session_name($name);

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => (string) $this->config->get('session', 'path', '/'),
            'domain'   => '',
            'secure'   => (bool) $this->config->get('session', 'secure', false),
            'httponly' => (bool) $this->config->get('session', 'httponly', true),
            'samesite' => (string) $this->config->get('session', 'samesite', 'Lax'),
        ]);

        if (!session_start()) {
            throw new SessionException('Не удалось инициализировать PHP-сессию');
        }
    }

    private function configureRedisHandler(): void
    {
        if (!extension_loaded('redis')) {
            throw new SessionException('Для работы сессий через Redis требуется PHP-расширение ext-redis');
        }

        $host = (string) $this->config->get('redis', 'host', '127.0.0.1');
        $port = (int) $this->config->get('redis', 'port', 6379);
        $password = (string) $this->config->get('redis', 'password', '');
        $database = (int) $this->config->get('redis', 'database', 0);
        $prefix = RedisNamespace::forSite(
            (string) $this->config->get('redis', 'prefix', 'vengine:'),
            (string) $this->config->env('APP_SITE', 'Corporate'),
        ) . 'sess:';

        $query = [
            'database'     => $database,
            'prefix'       => $prefix,
            'timeout'      => (float) $this->config->get('redis', 'connect_timeout', 1.0),
            'read_timeout' => (float) $this->config->get('redis', 'timeout', 0.5),
        ];

        if ($password !== '') {
            $query['auth'] = $password;
        }

        $savePath = sprintf('tcp://%s:%d?%s', $host, $port, http_build_query($query));

        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $savePath);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $this->map->get($_SESSION, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->map->set($_SESSION, $key, $value);
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return $this->map->has($_SESSION, $key);
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        $segments = explode('.', $key);
        $target = &$_SESSION;

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }
            $target = &$target[$segment];
        }

        unset($target[array_shift($segments)]);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!$this->hasSessionToRead()) {
            return $default;
        }

        $this->ensureStarted();
        if (!isset($_SESSION[self::FLASH_KEY]) || !array_key_exists($key, $_SESSION[self::FLASH_KEY])) {
            return $default;
        }

        $value = $_SESSION[self::FLASH_KEY][$key];
        unset($_SESSION[self::FLASH_KEY][$key]);
        return $value;
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        if (!session_regenerate_id($deleteOldSession)) {
            throw new SessionException('Не удалось регенерировать идентификатор сессии');
        }
    }

    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
            throw new SessionException('Не удалось сохранить и закрыть сессию');
        }
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
        }

        session_destroy();
    }

    private function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }
    }

    private function hasSessionToRead(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE || session_id() !== '') {
            return true;
        }

        $name = (string) $this->config->get('session', 'name', 'VENGINE_SESSID');
        return isset($_COOKIE[$name]) && $_COOKIE[$name] !== '';
    }
}
