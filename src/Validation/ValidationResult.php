<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

final readonly class ValidationResult
{
    /**
     * @param list<array{field: string, rule: string, message: string, params: array}> $errors
     */
    public function __construct(
        public bool $valid,
        public mixed $data,
        public array $errors,
    ) {
    }

    public function orFail(): mixed
    {
        if (!$this->valid) {
            throw ValidationException::fromErrors($this->errors);
        }

        return $this->data;
    }
}
