<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly ViewManager $manager,
        private readonly string $name,
        private array $data,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function render(): string
    {
        return $this->manager->render($this->name, $this->data);
    }
}
