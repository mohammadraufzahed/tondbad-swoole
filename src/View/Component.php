<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

abstract class Component
{
    public ComponentAttributeBag $attributes;

    protected array $slots = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->attributes = new ComponentAttributeBag();

        foreach ($data as $key => $value) {
            if (property_exists($this, $key) && $key !== 'attributes' && $key !== 'slots' && $key !== 'view') {
                $this->$key = $value;
            } elseif ($key === 'attributes' && is_array($value)) {
                $this->attributes = new ComponentAttributeBag($value);
            } else {
                $this->attributes->set($key, $value);
            }
        }
    }

    public static function create(array $data = []): static
    {
        return new static($data);
    }

    /**
     * @param array<string, \Closure> $slots
     */
    public function withSlots(array $slots): static
    {
        $this->slots = $slots;

        return $this;
    }

    public function slot(string $name = 'default'): string
    {
        return ($this->slots[$name] ?? fn () => '')();
    }

    public function __get(string $name): mixed
    {
        return $this->attributes->get($name);
    }

    abstract public function render(): View|string;
}
