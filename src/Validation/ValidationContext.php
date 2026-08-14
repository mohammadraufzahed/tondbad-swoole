<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation;

use TondbadSwoole\Database\DatabaseManager;

class ValidationContext
{
    public function __construct(
        private readonly string $attribute,
        private readonly array $data,
        private readonly ErrorBag $errorBag,
        private readonly ?DatabaseManager $databaseManager = null,
        private readonly array $messages = [],
        private readonly bool $bail = false,
    ) {
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDatabaseManager(): ?DatabaseManager
    {
        return $this->databaseManager;
    }

    public function withAttribute(string $attribute, ?array $data = null): self
    {
        return new self(
            $attribute,
            $data ?? $this->data,
            $this->errorBag,
            $this->databaseManager,
            $this->messages,
            $this->bail,
        );
    }

    public function addError(string $rule, string $message, array $params = []): void
    {
        $key = $this->attribute . '.' . $rule;
        $message = $this->messages[$key] ?? $this->messages[$rule] ?? $message;
        $message = $this->replacePlaceholders($message, $params);

        $this->errorBag->add([
            'field' => $this->attribute,
            'rule' => $rule,
            'message' => $message,
            'params' => $params,
        ]);
    }

    public function hasErrors(): bool
    {
        return $this->errorBag->hasErrors();
    }

    public function shouldBail(): bool
    {
        return $this->bail;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function replacePlaceholders(string $message, array $params): string
    {
        $message = str_replace(':attribute', str_replace(['_', '.'], ' ', $this->attribute), $message);

        foreach ($params as $index => $param) {
            $message = str_replace(":param{$index}", (string) $param, $message);
        }

        $aliases = [':min', ':max', ':value', ':format', ':other'];
        foreach ($params as $index => $param) {
            if (isset($aliases[$index])) {
                $message = str_replace($aliases[$index], (string) $param, $message);
            }
        }

        return $message;
    }
}
