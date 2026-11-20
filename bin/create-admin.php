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
        $powerShellScript = '$secure = Read-Host -AsSecureString; '
            . '$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
            . 'try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }';

        $candidates = [];
        $powerShellHome = getenv('PSHOME');
        if (is_string($powerShellHome) && $powerShellHome !== '') {
            $candidates[] = $powerShellHome . DIRECTORY_SEPARATOR . 'powershell.exe';
        }

        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR');
        if (is_string($systemRoot) && $systemRoot !== '') {
            $candidates[] = $systemRoot . '\System32\WindowsPowerShell\v1.0\powershell.exe';
            $candidates[] = $systemRoot . '\Sysnative\WindowsPowerShell\v1.0\powershell.exe';
        }

        $candidates[] = 'powershell.exe';
        $candidates[] = 'pwsh.exe';

        foreach (array_unique($candidates) as $executable) {
            $pipes = [];
            $process = @proc_open(
                [$executable, '-NoProfile', '-Command', $powerShellScript],
                [
                    0 => ['file', 'NUL', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true],
            );

            if (!is_resource($process)) {
                continue;
            }

            $password = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode === 0 && is_string($password)) {
                return rtrim($password, "\r\n");
            }
        }

        throw new RuntimeException(
            'Не удалось запустить PowerShell для скрытого ввода пароля. '
            . 'Установите PowerShell и убедитесь, что powershell.exe или pwsh.exe доступен PHP.',
        );
    }

    if (function_exists('shell_exec') && is_string(shell_exec('command -v stty'))) {
        try {
            shell_exec('stty -echo');
            $line = fgets(STDIN);
        } finally {
            shell_exec('stty echo');
            echo "\n";
        }
        return $line !== false ? trim($line) : '';
    }

    $line = fgets(STDIN);
    return $line !== false ? trim($line) : '';
}
