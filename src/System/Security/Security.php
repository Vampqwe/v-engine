<?php

declare(strict_types=1);

namespace VEngine\System\Security;

use Throwable;
use VEngine\System\Session\SessionInterface;

final class Security implements SecurityInterface
{
    private const CSRF_SESSION_KEY = '_vengine_csrf_token';

    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function hashPassword(string $password): string
    {
        if ($password === '') {
            throw new SecurityException('Пароль не может быть пустым', 400);
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    public function randomToken(int $bytes = 32): string
    {
        if ($bytes < 8) {
            throw new SecurityException('Длина токена должна быть не менее 8 байт', 500);
        }

        try {
            return bin2hex(random_bytes($bytes));
        } catch (Throwable $e) {
            throw new SecurityException('Не удалось сгенерировать криптографический токен', 500, $e);
        }
    }

    public function csrfToken(): string
    {
        $token = $this->session->get(self::CSRF_SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = $this->randomToken(32);
            $this->session->set(self::CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    public function rotateCsrfToken(): string
    {
        $token = $this->randomToken(32);
        $this->session->set(self::CSRF_SESSION_KEY, $token);

        return $token;
    }

    public function verifyCsrfToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $stored = $this->session->get(self::CSRF_SESSION_KEY);
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
