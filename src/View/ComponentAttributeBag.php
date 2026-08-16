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
        return classNames(array_merge($this->attributes['class'] ?? [], $classes));
    }

    public function __toString(): string
    {
        return attributeString($this->attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
