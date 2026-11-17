<?php

declare(strict_types=1);

namespace VEngine\System\Validator;

interface ValidatorInterface
{
    /**
     * Проверяет данные по набору правил.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string>> $rules
     * @param array<string, string> $messages Кастомные тексты ошибок (например, ['email.required' => 'Укажите почту'])
     * @return bool Возвращает true, если ошибок нет
     */
    public function validate(array $data, array $rules, array $messages = []): bool;

    /**
     * Проверяет данные и выбрасывает ValidationException при наличии ошибок.
     * Возвращает только проверенные (описанные в правилах) поля.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validateOrFail(array $data, array $rules, array $messages = []): array;

    /**
     * @return array<string, string[]>
     */
    public function errors(): array;

    public function firstError(string $field): ?string;
}
