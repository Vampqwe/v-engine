<?php

declare(strict_types=1);

namespace VEngine\System\Security;

interface SecurityInterface
{
    public function hashPassword(string $password): string;
    public function verifyPassword(string $password, string $hash): bool;
    public function needsRehash(string $hash): bool;
    public function randomToken(int $bytes = 32): string;
    public function csrfToken(): string;
    public function rotateCsrfToken(): string;
    public function verifyCsrfToken(?string $token): bool;
    public function escape(string $value): string;
}
