<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use ReflectionClass;
use ReflectionProperty;
use TondbadSwoole\View\Component;
use TondbadSwoole\View\View;

abstract class LiveComponent extends Component
{
    private ?string $token = null;

    private ?StateStore $store = null;

    public function mount(): void
    {
    }

    public function setStateToken(string $token, StateStore $store): void
    {
        $this->token = $token;
        $this->store = $store;
    }

    public function stateToken(): ?string
    {
        return $this->token;
    }

    public function stateStore(): ?StateStore
    {
        return $this->store;
    }

    public function refresh(): void
    {
        if ($this->token !== null && $this->store !== null) {
            $this->store->save($this->state());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getName() === 'attributes') {
                continue;
            }

            $state[$property->getName()] = $property->getValue($this);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function hydrate(array $state): void
    {
        foreach ($state as $key => $value) {
            if (property_exists($this, $key) && !is_null($value)) {
                $this->$key = $value;
            }
        }
    }

    public function runAction(string $name, array $params = []): void
    {
        if (!method_exists($this, $name)) {
            return;
        }

        $this->$name(...$params);
    }

    abstract public function render(): View;

    public function renderView(): string
    {
        return $this->render()->render();
    }
}
