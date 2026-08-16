<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use ReflectionClass;
use ReflectionMethod;
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
        $allowed = $this->publicSubclassProperties();

        foreach ($state as $key => $value) {
            if (isset($allowed[$key]) && !is_null($value)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public function syncInputs(array $inputs): void
    {
        $allowed = $this->publicSubclassProperties();

        foreach ($inputs as $key => $value) {
            if (!str_starts_with($key, 't:model:')) {
                continue;
            }

            $property = substr($key, strlen('t:model:'));

            if (isset($allowed[$property])) {
                $this->$property = $value;
            }
        }
    }

    public function runAction(string $name, array $params = []): void
    {
        $allowed = $this->allowedActions();

        if (!in_array($name, $allowed, true) || !method_exists($this, $name)) {
            return;
        }

        $method = new ReflectionMethod($this, $name);
        $arguments = [];

        foreach ($method->getParameters() as $index => $parameter) {
            $arguments[] = $params[$parameter->getName()]
                ?? $params[$index]
                ?? ($parameter->isOptional() ? $parameter->getDefaultValue() : null);
        }

        $method->invokeArgs($this, $arguments);
    }

    /**
     * @return array<string, bool>
     */
    private function publicSubclassProperties(): array
    {
        $properties = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getName() === 'attributes') {
                continue;
            }

            if ($property->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            $properties[$property->getName()] = true;
        }

        return $properties;
    }

    /**
     * @return list<string>
     */
    private function allowedActions(): array
    {
        $actions = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getDeclaringClass()->getName() !== static::class) {
                continue;
            }

            if (in_array($method->getName(), ['mount', 'render'], true)) {
                continue;
            }

            $actions[] = $method->getName();
        }

        return $actions;
    }

    abstract public function render(): View;

    public function renderView(): string
    {
        return $this->render()->render();
    }
}
