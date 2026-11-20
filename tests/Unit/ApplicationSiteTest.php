<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use VEngine\Kernel\Application;

final class ApplicationSiteTest extends TestCase
{
    private array $previousSite;

    protected function setUp(): void
    {
        $this->previousSite = [
            'envExists'    => array_key_exists('APP_SITE', $_ENV),
            'envValue'     => $_ENV['APP_SITE'] ?? null,
            'serverExists' => array_key_exists('APP_SITE', $_SERVER),
            'serverValue'  => $_SERVER['APP_SITE'] ?? null,
        ];
        unset($_ENV['APP_SITE'], $_SERVER['APP_SITE']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_SITE'], $_SERVER['APP_SITE']);
        if ($this->previousSite['envExists']) {
            $_ENV['APP_SITE'] = $this->previousSite['envValue'];
        }
        if ($this->previousSite['serverExists']) {
            $_SERVER['APP_SITE'] = $this->previousSite['serverValue'];
        }
    }

    public function testSelectsTheDefaultSiteAndExposesSitePaths(): void
    {
        $application = new Application(dirname(__DIR__, 2));
        $container = $application->getContainer();

        self::assertSame('Corporate', $container->get('path.site'));
        self::assertSame(dirname(__DIR__, 2) . '/sites/Corporate', $container->get('path.siteRoot'));
        self::assertSame(
            dirname(__DIR__, 2) . '/sites/Corporate/public',
            $container->get('path.public'),
        );
        self::assertSame(
            dirname(__DIR__, 2) . '/sites/Corporate/App',
            $container->get('path.siteApp'),
        );
        self::assertSame(
            dirname(__DIR__, 2) . '/storage/cache/sites/Corporate',
            $container->get('path.cache'),
        );
    }

    public function testRejectsPathTraversalInSiteName(): void
    {
        $_ENV['APP_SITE'] = '../config';

        $this->expectException(\InvalidArgumentException::class);
        new Application(dirname(__DIR__, 2));
    }

    public function testRejectsUnknownSite(): void
    {
        $_ENV['APP_SITE'] = 'MissingSite';

        $this->expectException(\InvalidArgumentException::class);
        new Application(dirname(__DIR__, 2));
    }
}
