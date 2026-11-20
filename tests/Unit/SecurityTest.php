<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\System\Security\Security;
use VEngine\System\Session\SessionInterface;

final class SecurityTest extends TestCase
{
    public function testCsrfTokenRotationInvalidatesThePreviousToken(): void
    {
        $storedToken = null;
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturnCallback(
            function (string $key, mixed $default = null) use (&$storedToken): mixed {
                return $storedToken ?? $default;
            },
        );
        $session->method('set')->willReturnCallback(
            static function (string $key, mixed $value) use (&$storedToken): void {
                $storedToken = $value;
            },
        );

        $security = new Security($session);
        $previousToken = $security->csrfToken();
        $newToken = $security->rotateCsrfToken();

        self::assertNotSame($previousToken, $newToken);
        self::assertFalse($security->verifyCsrfToken($previousToken));
        self::assertTrue($security->verifyCsrfToken($newToken));
    }
}
