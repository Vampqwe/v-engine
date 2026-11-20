#!/usr/bin/env php
<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Modules\Admin\Services\AdminUserService;
use VEngine\Kernel\Application;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Validator\ValidatorInterface;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Этот скрипт можно запускать только из командной строки (CLI).\n");
    exit(1);
}

if ($argc > 3) {
    fwrite(STDERR, "Передавайте только email и имя; пароль вводится отдельно скрытым вводом.\n");
    exit(2);
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

$app = new Application($basePath);
$container = $app->getContainer();

/** @var DataBaseInterface $db */
$db = $container->get(DataBaseInterface::class);
/** @var ValidatorInterface $validator */
$validator = $container->get(ValidatorInterface::class);
/** @var AdminUserService $adminService */
$adminService = $container->get(AdminUserService::class);

echo "=== Создание администратора V-engine ===\n\n";

try {
    // 1. Автоматически создаем таблицу admin_users, если она еще не развернута
    $db->query(<<<'SQL'
        CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL DEFAULT 'Admin',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL);

    // 2. Считываем аргументы из командной строки или запрашиваем интерактивно
    $email = $argv[1] ?? prompt('Введите Email администратора: ');
    $name = $argv[2] ?? prompt('Введите имя [Admin]: ', 'Admin');
    $password = promptHidden('Введите пароль (минимум 8 символов): ');

    // 3. Валидируем данные штатным валидатором (включая проверку unique в БД)
    $data = [
        'email'    => trim($email),
        'name'     => trim($name),
        'password' => $password,
    ];

    $isValid = $validator->validate($data, [
        'email'    => 'required|email|unique:admin_users,email',
        'name'     => 'required|string|min:2|max:100',
        'password' => 'required|string|min:8',
    ], [
        'email.required'    => 'Email обязателен для заполнения.',
        'email.email'       => 'Указан некорректный формат Email.',
        'email.unique'      => 'Администратор с таким Email уже существует.',
        'name.min'          => 'Имя должно содержать минимум 2 символа.',
        'password.required' => 'Пароль не может быть пустым.',
        'password.min'      => 'Пароль должен содержать минимум 8 символов.',
    ]);

    if (!$isValid) {
        fwrite(STDERR, "\n[Ошибка валидации]:\n");
        foreach ($validator->errors() as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                fwrite(STDERR, " - {$error}\n");
            }
        }
        exit(1);
    }

    // 4. Сохраняем администратора
    $adminService->createAdmin($data['email'], $data['password'], $data['name']);

    echo "\n[OK] Администратор '{$data['name']}' ({$data['email']}) успешно создан!\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "\n[Критическая ошибка]: {$e->getMessage()}\n");
    exit(1);
}

/**
 * Запрашивает текстовый ввод в терминале.
 */
function prompt(string $label, string $default = ''): string
{
    echo $label;
    $line = fgets(STDIN);
    $value = $line !== false ? trim($line) : '';
    return $value !== '' ? $value : $default;
}

/**
 * Скрытый ввод пароля (поддерживает Linux/macOS и Windows).
 */
function promptHidden(string $label): string
{
    echo $label;

    if (DIRECTORY_SEPARATOR === '\\') {
        $command = 'powershell -Command "$p = Read-Host -AsSecureString; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
            . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $password = shell_exec($command);
        return is_string($password) ? trim($password) : '';
    }

    if (function_exists('shell_exec') && is_string(shell_exec('command -v stty'))) {
        shell_exec('stty -echo');
        $line = fgets(STDIN);
        shell_exec('stty echo');
        echo "\n";
        return $line !== false ? trim($line) : '';
    }

    $line = fgets(STDIN);
    return $line !== false ? trim($line) : '';
}
