<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Admin\Services\AdminUserService;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Security\SecurityInterface;

final class AdminUserServiceTest extends TestCase
{
    public function testUnknownEmailStillVerifiesPasswordAgainstDummyHash(): void
    {
        $defaultHashOptions = password_get_info(password_hash('runtime-default', PASSWORD_DEFAULT))['options'];
        $expectedCost = $defaultHashOptions['cost'] ?? null;

        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetch')->willReturn(false);

        $database = $this->createMock(DataBaseInterface::class);
        $database->method('query')->willReturn($statement);

        $security = $this->createMock(SecurityInterface::class);
        $security->expects(self::once())
            ->method('verifyPassword')
            ->with('candidate-password', self::callback(
                static fn(string $hash): bool => password_get_info($hash)['algoName'] === 'bcrypt'
                    && password_get_info($hash)['options']['cost'] === $expectedCost,
            ))
            ->willReturn(false);

        $service = new AdminUserService($database, $security);

        self::assertNull($service->authenticate('unknown@example.test', 'candidate-password'));
    }
}
