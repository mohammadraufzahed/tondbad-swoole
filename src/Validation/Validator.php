<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

use DateTimeImmutable;
use TondbadSwoole\Database\DatabaseManager;

class Validator
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly ?DatabaseManager $databaseManager = null,
    ) {
    }

    public function fails(): bool
    {
        $this->validate();

        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        $validated = [];

        foreach ($this->rules as $attribute => $rules) {
            if (array_key_exists($attribute, $this->data)) {
                $validated[$attribute] = $this->data[$attribute];
            }
        }

        return $validated;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $this->validate();

        return $this->errors;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors();
    }

    private function validate(): void
    {
        if (!empty($this->errors)) {
            return;
        }

        foreach ($this->rules as $attribute => $rules) {
            $this->validateAttribute($attribute, $rules);
        }
    }

    /**
     * @param string|list<string> $rules
     */
    private function validateAttribute(string $attribute, mixed $rules): void
    {
        if (!is_array($rules)) {
            $rules = explode('|', (string) $rules);
        }

        $value = $this->data[$attribute] ?? null;
        $hasAttribute = array_key_exists($attribute, $this->data);

        if (in_array('sometimes', $rules, true) && !$hasAttribute) {
            return;
        }

        $bail = false;

        foreach ($rules as $rule) {
            $rule = trim((string) $rule);

            if ($rule === '') {
                continue;
            }

            if ($rule === 'bail') {
                $bail = true;

                continue;
            }

            if (in_array($rule, ['sometimes'], true)) {
                continue;
            }

            if ($rule === 'nullable' && ($value === null || $value === '')) {
                break;
            }

            if ($rule === 'nullable') {
                continue;
            }

            if (!$this->passesRule($attribute, $value, $rule)) {
                $this->addError($attribute, $rule);

                if ($bail) {
                    break;
                }
            }
        }
    }

    private function passesRule(string $attribute, mixed $value, string $rule): bool
    {
        [$name, $parameters] = $this->parseRule($rule);

        return match ($name) {
            'required' => $this->validateRequired($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'string' => is_string($value),
            'int', 'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null,
            'array' => is_array($value),
            'min' => $this->validateMin($value, $parameters),
            'max' => $this->validateMax($value, $parameters),
            'in' => in_array($value, $parameters, true),
            'not_in' => !in_array($value, $parameters, true),
            'regex' => $this->validateRegex($value, $parameters),
            'confirmed' => isset($this->data["{$attribute}_confirmation"]) && $this->data["{$attribute}_confirmation"] === $value,
            'same' => isset($this->data[$parameters[0] ?? '']) && $this->data[$parameters[0] ?? ''] === $value,
            'different' => !isset($this->data[$parameters[0] ?? '']) || $this->data[$parameters[0] ?? ''] !== $value,
            'unique' => $this->validateUnique($attribute, $value, $parameters),
            'exists' => $this->validateExists($attribute, $value, $parameters),
            'date' => is_string($value) && strtotime($value) !== false,
            'date_format' => $this->validateDateFormat($value, $parameters),
            'before' => $this->validateBefore($value, $parameters),
            'after' => $this->validateAfter($value, $parameters),
            'uuid' => $this->validateUuid($value),
            'ip' => filter_var($value, FILTER_VALIDATE_IP) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'alpha' => is_string($value) && ctype_alpha($value),
            'alpha_num' => is_string($value) && ctype_alnum($value),
            'alpha_dash' => is_string($value) && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1,
            'json' => $this->validateJson($value),
            'digits' => is_string($value) && ctype_digit($value),
            'digits_between' => $this->validateDigitsBetween($value, $parameters),
            default => true,
        };
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $params] = explode(':', $rule, 2);

            return [trim($name), array_map('trim', explode(',', $params))];
        }

        return [trim($rule), []];
    }

    private function validateRequired(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateMin(mixed $value, array $parameters): bool
    {
        $limit = (int) ($parameters[0] ?? 0);

        if (is_array($value)) {
            return count($value) >= $limit;
        }

        if (is_numeric($value) && !is_bool($value)) {
            return (float) $value >= $limit;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $limit;
        }

        return true;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateMax(mixed $value, array $parameters): bool
    {
        $limit = (int) ($parameters[0] ?? 0);

        if (is_array($value)) {
            return count($value) <= $limit;
        }

        if (is_numeric($value) && !is_bool($value)) {
            return (float) $value <= $limit;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $limit;
        }

        return true;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateRegex(mixed $value, array $parameters): bool
    {
        if (!is_string($value) || count($parameters) === 0) {
            return false;
        }

        return @preg_match($parameters[0], $value) === 1;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateDateFormat(mixed $value, array $parameters): bool
    {
        if (!is_string($value) || count($parameters) === 0) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat($parameters[0], $value);

        return $date !== false && $date->format($parameters[0]) === $value;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateBefore(mixed $value, array $parameters): bool
    {
        if (!is_string($value) || count($parameters) === 0) {
            return false;
        }

        $other = $parameters[0];

        if (array_key_exists($other, $this->data)) {
            $other = $this->data[$other];
        }

        return strtotime($value) < strtotime($other);
    }

    /**
     * @param list<string> $parameters
     */
    private function validateAfter(mixed $value, array $parameters): bool
    {
        if (!is_string($value) || count($parameters) === 0) {
            return false;
        }

        $other = $parameters[0];

        if (array_key_exists($other, $this->data)) {
            $other = $this->data[$other];
        }

        return strtotime($value) > strtotime($other);
    }

    private function validateUuid(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1;
    }

    private function validateJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateDigitsBetween(mixed $value, array $parameters): bool
    {
        if (!is_string($value) || count($parameters) < 2) {
            return false;
        }

        if (!ctype_digit($value)) {
            return false;
        }

        $min = (int) $parameters[0];
        $max = (int) $parameters[1];
        $length = strlen($value);

        return $length >= $min && $length <= $max;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateUnique(string $attribute, mixed $value, array $parameters): bool
    {
        if ($this->databaseManager === null) {
            return true;
        }

        if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
            return false;
        }

        $table = $parameters[0] ?? '';
        $column = $parameters[1] ?? $attribute;

        if ($table === '') {
            return true;
        }

        $connection = $this->databaseManager->connection();

        $result = $connection->table($table)->where($column, '=', (string) $value)->count();

        return $result === 0;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateExists(string $attribute, mixed $value, array $parameters): bool
    {
        if ($this->databaseManager === null) {
            return true;
        }

        if ($value === null) {
            return false;
        }

        $table = $parameters[0] ?? '';
        $column = $parameters[1] ?? $attribute;

        if ($table === '') {
            return true;
        }

        $connection = $this->databaseManager->connection();

        $result = $connection->table($table)->where($column, '=', (string) $value)->count();

        return $result > 0;
    }

    private function addError(string $attribute, string $rule): void
    {
        $this->errors[$attribute][] = $this->resolveMessage($attribute, $rule);
    }

    private function resolveMessage(string $attribute, string $rule): string
    {
        $key = "{$attribute}.{$rule}";

        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        if (isset($this->messages[$rule])) {
            return $this->messages[$rule];
        }

        $name = $this->parseRule($rule)[0];

        $replacements = [
            ':attribute' => str_replace(['_', '.'], ' ', $attribute),
        ];

        if (str_contains($rule, ':')) {
            [, $params] = explode(':', $rule, 2);
            $params = explode(',', $params);

            foreach ($params as $index => $param) {
                $replacements[":param{$index}"] = $param;
                $replacements[[':min', ':max', ':value', ':other'][$index] ?? ":param{$index}"] = $param;
            }
        }

        $message = $this->defaultMessage($name);

        return strtr($message, $replacements);
    }

    private function defaultMessage(string $rule): string
    {
        return match ($rule) {
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'string' => 'The :attribute must be a string.',
            'int', 'integer' => 'The :attribute must be an integer.',
            'numeric' => 'The :attribute must be numeric.',
            'bool', 'boolean' => 'The :attribute must be a boolean.',
            'array' => 'The :attribute must be an array.',
            'min' => 'The :attribute must be at least :min.',
            'max' => 'The :attribute may not be greater than :max.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'same' => 'The :attribute and :other must match.',
            'different' => 'The :attribute and :other must be different.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'date' => 'The :attribute is not a valid date.',
            'date_format' => 'The :attribute does not match the format :value.',
            'before' => 'The :attribute must be a date before :value.',
            'after' => 'The :attribute must be a date after :value.',
            'uuid' => 'The :attribute must be a valid UUID.',
            'ip' => 'The :attribute must be a valid IP address.',
            'url' => 'The :attribute must be a valid URL.',
            'alpha' => 'The :attribute may only contain letters.',
            'alpha_num' => 'The :attribute may only contain letters and numbers.',
            'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
            'json' => 'The :attribute must be a valid JSON string.',
            'digits' => 'The :attribute must be digits.',
            'digits_between' => 'The :attribute must have between :param0 and :param1 digits.',
            default => 'The :attribute field is invalid.',
        };
    }
}
