<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

final class ComponentAttributeBag
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private array $attributes = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function merge(array $attributes): self
    {
        return new self(array_merge($this->attributes, $attributes));
    }

    /**
     * @param list<string> $keys
     */
    public function except(array $keys): self
    {
        return new self(array_diff_key($this->attributes, array_flip($keys)));
    }

    /**
     * @param list<string> $keys
     */
    public function only(array $keys): self
    {
        return new self(array_intersect_key($this->attributes, array_flip($keys)));
    }

    public function class(array $classes): string
    {
        $class = $this->attributes['class'] ?? [];

        if (is_string($class)) {
            $class = array_values(array_filter(explode(' ', $class)));
        }

        if (!is_array($class)) {
            $class = [];
        }

        return classNames(array_merge($class, $classes));
    }

    public function __toString(): string
    {
        $attributes = array_filter(
            $this->attributes,
            static fn (string $key): bool => !str_starts_with($key, '__'),
            ARRAY_FILTER_USE_KEY,
        );

        return attributeString($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
