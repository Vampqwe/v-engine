<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Modules\Admin\Services\AdminUserService;
use VEngine\System\Console\Command;
use VEngine\System\Console\InputInterface;
use VEngine\System\Console\OutputInterface;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Validator\ValidatorInterface;

final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly DataBaseInterface $db,
        private readonly ValidatorInterface $validator,
        private readonly AdminUserService $adminService,
    ) {
        parent::__construct(
            'admin:create',
            'Создать учётную запись администратора.',
            arguments: [
                'email' => ['description' => 'Email администратора.'],
                'name'  => ['description' => 'Имя администратора.'],
            ],
            extraArgumentsMessage: 'пароль вводится отдельно и не передается аргументом процесса.',
        );
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `admin_users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `name` VARCHAR(100) NOT NULL DEFAULT 'Admin',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        $output->writeln('=== Создание администратора V-engine ===');
        $email = $input->argument('email') ?? $input->ask('Введите Email администратора: ');
        $name = $input->argument('name') ?? $input->ask('Введите имя [Admin]: ', 'Admin');
        $password = $input->askHidden('Введите пароль (минимум 8 символов): ');
        $data = [
            'email'    => trim($email),
            'name'     => trim($name),
            'password' => $password,
        ];

        if (!$this->validator->validate($data, [
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
        ])) {
            $output->error('Ошибка валидации:');
            foreach ($this->validator->errors() as $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $output->error(' - ' . $error);
                }
            }
            return 1;
        }

        $this->adminService->createAdmin($data['email'], $data['password'], $data['name']);
        $output->writeln("[OK] Администратор '{$data['name']}' ({$data['email']}) успешно создан!");
        return 0;
    }
}
