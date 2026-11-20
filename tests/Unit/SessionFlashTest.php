<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Map\Map;
use VEngine\System\Session\Session;

final class SessionFlashTest extends TestCase
{
    public function testReadingFlashWithoutAnIncomingSessionDoesNotStartOne(): void
    {
        $previousCookies = $_COOKIE;
        unset($_COOKIE['VENGINE_SESSID']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $session = new Session(new SessionTestConfig(), new Map());

            self::assertSame('default', $session->getFlash('notice', 'default'));
            self::assertSame(PHP_SESSION_NONE, session_status());
        } finally {
            $_COOKIE = $previousCookies;
        }
    }

    public function testReadingFlashFromAClosedSessionClosesTheResumedSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $directory = sys_get_temp_dir() . '/vengine-session-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o700, true);
        $previousSavePath = session_save_path();
        $previousName = session_name();
        $previousSessionId = session_id();

        try {
            session_save_path($directory);
            session_name('VENGINE_TEST_SESSID');
            session_id('test' . bin2hex(random_bytes(8)));
            session_start();
            $_SESSION['_vengine_flash']['notice'] = 'saved';
            session_write_close();

            $session = new Session(new SessionTestConfig(), new Map());

            self::assertSame('saved', $session->getFlash('notice'));
            self::assertSame(PHP_SESSION_NONE, session_status());

            session_start();
            self::assertArrayNotHasKey('notice', $_SESSION['_vengine_flash'] ?? []);
            session_write_close();
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id($previousSessionId);
            session_name($previousName);
            session_save_path($previousSavePath);
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}

final class SessionTestConfig implements ConfigInterface
{
    public function env(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function get(string $file, string $key, mixed $default = null): mixed
    {
        if ($file === 'session' && $key === 'name') {
            return 'VENGINE_TEST_SESSID';
        }
        return $default;
    }
}
