<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

class ErrorBag
{
    /**
     * @var list<array{field: string, rule: string, message: string, params: array}>
     */
    private array $errors = [];

    public function add(array $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return list<array{field: string, rule: string, message: string, params: array}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
