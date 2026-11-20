<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Error\ErrorHandler;
use VEngine\System\Error\ErrorRendererInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Logger\LoggerInterface;
use VEngine\System\Session\SessionInterface;
use VEngine\System\Validator\ValidationException;

final class ErrorHandlerTest extends TestCase
{
    private int|false $previousStatus;

    protected function setUp(): void
    {
        $this->previousStatus = http_response_code();
    }

    protected function tearDown(): void
    {
        if ($this->previousStatus !== false) {
            http_response_code($this->previousStatus);
        }
    }

    public function testExceptionAfterResponseWasSentDoesNotWriteASecondBody(): void
    {
        $this->expectOutputString('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('forModule')->willReturn($logger);
        $logger->expects(self::once())->method('error');
        $handler = $this->makeHandler($this->createMock(SessionInterface::class), $logger);
        $handler->markResponseSent();

        $method = new \ReflectionMethod(ErrorHandler::class, 'handleException');
        $method->invoke($handler, new RuntimeException('Shutdown failure'));
    }

    public function testValidationExceptionAfterResponseWasSentDoesNotWriteAnotherResponse(): void
    {
        $this->expectOutputString('');

        $handler = $this->makeHandler($this->createMock(SessionInterface::class));
        $handler->markResponseSent();

        $method = new \ReflectionMethod(ErrorHandler::class, 'handleException');
        $method->invoke($handler, new ValidationException(['email' => ['Invalid email']]));
    }

    private function makeHandler(
        SessionInterface $session,
        ?LoggerInterface $logger = null,
    ): ErrorHandler {
        $logger ??= $this->createMock(LoggerInterface::class);
        $logger->method('forModule')->willReturn($logger);

        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturn(false);

        $request = new class implements RequestInterface {
            public function method(): string
            {
                return 'POST';
            }

            public function uri(): string
            {
                return '/submit';
            }

            public function query(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function input(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function all(): array
            {
                return [];
            }

            public function header(string $name, mixed $default = null): mixed
            {
                return $default;
            }

            public function isAjax(): bool
            {
                return false;
            }

            public function withAttribute(string $name, mixed $value): self
            {
                return $this;
            }

            public function attribute(string $name, mixed $default = null): mixed
            {
                return $default;
            }

            public function ip(): string
            {
                return '127.0.0.1';
            }
        };

        return new ErrorHandler(
            $logger,
            $this->createMock(ErrorRendererInterface::class),
            $config,
            $request,
            $session,
        );
    }
}
