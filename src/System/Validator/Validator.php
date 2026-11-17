<?php

declare(strict_types=1);

namespace VEngine\System\Validator;

use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Map\MapInterface;

final class Validator implements ValidatorInterface
{
    /** @var array<string, string[]> */
    private array $errors = [];

    public function __construct(
        private readonly MapInterface $map,
        private readonly DataBaseInterface $db
    ) {}

    public function validate(array $data, array $rules, array $messages = []): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->map->get($data, $field);
            $isNullable = in_array('nullable', $ruleList, true);

            if ($isNullable && $this->isEmpty($value)) {
                continue;
            }

            foreach ($ruleList as $rawRule) {
                if ($rawRule === '' || $rawRule === 'nullable') {
                    continue;
                }

                [$ruleName, $param] = $this->parseRule($rawRule);
                $error = $this->applyRule($field, $value, $ruleName, $param, $data);

                if ($error !== null) {
                    $customKey = "{$field}.{$ruleName}";
                    $this->errors[$field][] = $messages[$customKey] ?? $messages[$field] ?? $error;
                }
            }
        }

        return empty($this->errors);
    }

    public function validateOrFail(array $data, array $rules, array $messages = []): array
    {
        if (!$this->validate($data, $rules, $messages)) {
            throw new ValidationException($this->errors);
        }

        $validated = [];
        foreach (array_keys($rules) as $field) {
            if ($this->map->has($data, $field)) {
                $this->map->set($validated, $field, $this->map->get($data, $field));
            }
        }

        return $validated;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param, array $allData): ?string
    {
        return match ($rule) {
            'required'  => $this->isEmpty($value) ? "Поле '{$field}' обязательно для заполнения." : null,
            'string'    => !is_string($value) ? "Поле '{$field}' должно быть строкой." : null,
            'int'       => filter_var($value, FILTER_VALIDATE_INT) === false ? "Поле '{$field}' должно быть целым числом." : null,
            'numeric'   => !is_numeric($value) ? "Поле '{$field}' должно быть числом." : null,
            'bool'      => !in_array($value, [true, false, 0, 1, '0', '1'], true) ? "Поле '{$field}' должно быть логическим значением." : null,
            'array'     => !is_array($value) ? "Поле '{$field}' должно быть массивом." : null,
            'email'     => (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) ? "Поле '{$field}' должно содержать корректный email." : null,
            'url'       => (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) ? "Поле '{$field}' должно содержать корректный URL." : null,
            'min'       => $this->checkMin($field, $value, (int) $param),
            'max'       => $this->checkMax($field, $value, (int) $param),
            'in'        => $this->checkIn($field, $value, (string) $param),
            'confirmed' => $this->checkConfirmed($field, $value, $allData),
            'regex'     => $this->checkRegex($field, $value, (string) $param),
            'unique'    => $this->checkUnique($field, $value, (string) $param),
            'exists'    => $this->checkExists($field, $value, (string) $param),
            default     => throw new ValidationException([$field => ["Неизвестное правило валидации: {$rule}"]], 'Ошибка конфигурации валидатора'),
        };
    }

    private function checkMin(string $field, mixed $value, int $min): ?string
    {
        if (is_string($value) && mb_strlen($value) < $min) {
            return "Поле '{$field}' должно содержать минимум {$min} символов.";
        }
        if ((is_int($value) || is_float($value)) && $value < $min) {
            return "Поле '{$field}' должно быть не меньше {$min}.";
        }
        if (is_array($value) && count($value) < $min) {
            return "Поле '{$field}' должно содержать минимум {$min} элементов.";
        }
        return null;
    }

    private function checkMax(string $field, mixed $value, int $max): ?string
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            return "Поле '{$field}' не должно превышать {$max} символов.";
        }
        if ((is_int($value) || is_float($value)) && $value > $max) {
            return "Поле '{$field}' не должно быть больше {$max}.";
        }
        if (is_array($value) && count($value) > $max) {
            return "Поле '{$field}' не должно содержать больше {$max} элементов.";
        }
        return null;
    }

    private function checkIn(string $field, mixed $value, string $param): ?string
    {
        $allowed = explode(',', $param);
        if (!in_array((string) $value, $allowed, true)) {
            return "Поле '{$field}' содержит недопустимое значение.";
        }
        return null;
    }

    private function checkConfirmed(string $field, mixed $value, array $allData): ?string
    {
        $confirmation = $this->map->get($allData, "{$field}_confirmation");
        if ($value !== $confirmation) {
            return "Подтверждение для поля '{$field}' не совпадает.";
        }
        return null;
    }

    private function checkRegex(string $field, mixed $value, string $pattern): ?string
    {
        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            return "Поле '{$field}' имеет неверный формат.";
        }
        return null;
    }

    /**
     * Формат: unique:table,column[,exceptId]
     */
    private function checkUnique(string $field, mixed $value, string $param): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $parts = explode(',', $param);
        $table = $this->sanitizeIdentifier($parts[0] ?? '');
        $column = $this->sanitizeIdentifier($parts[1] ?? $field);
        $exceptId = $parts[2] ?? null;

        if ($exceptId !== null) {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val AND `id` != :id",
                ['val' => $value, 'id' => $exceptId]
            );
        } else {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val",
                ['val' => $value]
            );
        }

        if ((int) $stmt->fetchColumn() > 0) {
            return "Значение поля '{$field}' уже занято.";
        }

        return null;
    }

    /**
     * Формат: exists:table,column
     */
    private function checkExists(string $field, mixed $value, string $param): ?string
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $parts = explode(',', $param);
        $table = $this->sanitizeIdentifier($parts[0] ?? '');
        $column = $this->sanitizeIdentifier($parts[1] ?? 'id');

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val",
            ['val' => $value]
        );

        if ((int) $stmt->fetchColumn() === 0) {
            return "Выбранное значение для поля '{$field}' не найдено.";
        }

        return null;
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $identifier) !== 1) {
            throw new ValidationException(['database' => ["Недопустимый идентификатор БД: {$identifier}"]], 'Ошибка конфигурации правила БД');
        }
        return $identifier;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function parseRule(string $rawRule): array
    {
        if (!str_contains($rawRule, ':')) {
            return [$rawRule, null];
        }
        [$name, $param] = explode(':', $rawRule, 2);
        return [$name, $param];
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
