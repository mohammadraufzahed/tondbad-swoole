<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

use Exception;
use Throwable;

class ValidationException extends Exception
{
    /**
     * @var list<array{field: string, rule: string, message: string, params: array}>
     */
    private readonly array $structuredErrors;

    /**
     * @var array<string, list<string>>
     */
    private array $legacyErrors = [];

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        array $errors,
        string $message = 'The given data was invalid.',
        int $code = 422,
        ?Throwable $previous = null,
    ) {
        if (isset($errors[0]) && is_array($errors[0]) && isset($errors[0]['field'])) {
            $this->structuredErrors = $errors;
            $legacy = $this->toLegacyErrors($errors);
        } else {
            /** @var array<string, list<string>> $legacyInput */
            $legacyInput = $errors;
            $this->structuredErrors = $this->toStructuredErrors($legacyInput);
            $legacy = $legacyInput;
        }

        parent::__construct($message, $code, $previous);

        // Keep legacy errors accessible via the protected $message? We use a dynamic property for legacy format.
        $this->legacyErrors = $legacy;
    }

    public static function fromErrors(array $errors): self
    {
        return new self($errors);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->legacyErrors ?? $this->toLegacyErrors($this->structuredErrors);
    }

    /**
     * @return list<array{field: string, rule: string, message: string, params: array}>
     */
    public function getStructuredErrors(): array
    {
        return $this->structuredErrors;
    }

    /**
     * @param array<string, list<string>> $legacy
     * @return list<array{field: string, rule: string, message: string, params: array}>
     */
    private function toStructuredErrors(array $legacy): array
    {
        $errors = [];
        foreach ($legacy as $field => $messages) {
            foreach ($messages as $msg) {
                $errors[] = [
                    'field' => $field,
                    'rule' => 'unknown',
                    'message' => $msg,
                    'params' => [],
                ];
            }
        }

        return $errors;
    }

    /**
     * @param list<array{field: string, rule: string, message: string, params: array}> $structured
     * @return array<string, list<string>>
     */
    private function toLegacyErrors(array $structured): array
    {
        $errors = [];
        foreach ($structured as $error) {
            $errors[$error['field']][] = $error['message'];
        }

        return $errors;
    }
}
