<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Security\SecurityInterface;

final class AdminUserService
{
    public function __construct(
        private readonly DataBaseInterface $db,
        private readonly SecurityInterface $security
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->query(
            'SELECT id, email, password_hash, name FROM `admin_users` WHERE `email` = :email LIMIT 1',
            ['email' => $email]
        );

        $user = $stmt->fetch();
        return is_array($user) ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->query(
            'SELECT id, email, name FROM `admin_users` WHERE `id` = :id LIMIT 1',
            ['id' => $id]
        );

        $user = $stmt->fetch();
        return is_array($user) ? $user : null;
    }

    /**
     * Проверяет учетные данные и возвращает данные администратора при успехе.
     *
     * @return array<string, mixed>|null
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!$this->security->verifyPassword($password, (string) $user['password_hash'])) {
            return null;
        }

        // Обновляем хеш пароля, если алгоритм в PHP обновился
        if ($this->security->needsRehash((string) $user['password_hash'])) {
            $newHash = $this->security->hashPassword($password);
            $this->db->query(
                'UPDATE `admin_users` SET `password_hash` = :hash WHERE `id` = :id',
                ['hash' => $newHash, 'id' => $user['id']]
            );
        }

        unset($user['password_hash']);
        return $user;
    }

    public function createAdmin(string $email, string $password, string $name = 'Admin'): void
    {
        $hash = $this->security->hashPassword($password);
        $this->db->query(
            'INSERT INTO `admin_users` (`email`, `password_hash`, `name`) VALUES (:email, :hash, :name)',
            [
                'email' => $email,
                'hash'  => $hash,
                'name'  => $name,
            ]
        );
    }
}
