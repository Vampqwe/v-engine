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
        self::assertSame(PHP_SESSION_NONE, session_status());

        $previousCookies = $_COOKIE;
        unset($_COOKIE['VENGINE_SESSID']);

        try {
            $session = new Session(new SessionTestConfig(), new Map());

            self::assertSame('default', $session->getFlash('notice', 'default'));
            self::assertSame(PHP_SESSION_NONE, session_status());
        } finally {
            $_COOKIE = $previousCookies;
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
        return $default;
    }
}
