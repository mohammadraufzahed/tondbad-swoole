<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use ReflectionClass;
use TondbadSwoole\Database\Attributes\OnCreate;
use TondbadSwoole\Database\Attributes\OnDelete;
use TondbadSwoole\Database\Attributes\OnFlush;
use TondbadSwoole\Database\Attributes\OnLoad;
use TondbadSwoole\Database\Attributes\OnUpdate;

class EntityEventManager
{
    /** @var list<object> */
    private array $subscribers = [];

    /** @var array<string, callable> */
    private array $listeners = [];

    /** @var array<class-string, array<string, list<\ReflectionMethod>>> */
    private array $hookMap = [];

    public function addEventSubscriber(object $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    public function addEventListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatchEvent(string $event, object $entity): void
    {
        $eventObject = new EntityEvent($event, $entity);

        $this->dispatchToListeners($event, $eventObject);
        $this->dispatchToSubscribers($event, $eventObject);
        $this->dispatchToEntityHooks($event, $entity, $eventObject);
    }

    private function dispatchToListeners(string $event, EntityEvent $eventObject): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($eventObject);
        }
    }

    private function dispatchToSubscribers(string $event, EntityEvent $eventObject): void
    {
        foreach ($this->subscribers as $subscriber) {
            if ($subscriber instanceof EntityEventSubscriber) {
                $method = $subscriber->getSubscribedEvents()[$event] ?? null;

                if ($method !== null && method_exists($subscriber, $method)) {
                    $subscriber->$method($eventObject);
                }

                continue;
            }

            if (method_exists($subscriber, $event)) {
                $subscriber->$event($eventObject);
            }
        }
    }

    private function dispatchToEntityHooks(string $event, object $entity, EntityEvent $eventObject): void
    {
        $class = $entity::class;
        $hooks = $this->hookMap[$class] ??= $this->buildHookMap($class);

        foreach ($hooks[$event] ?? [] as $method) {
            if ($method->getNumberOfParameters() === 0) {
                $method->invoke($entity);
            } else {
                $method->invoke($entity, $eventObject);
            }
        }
    }

    /**
     * @param class-string $class
     * @return array<string, list<\ReflectionMethod>>
     */
    private function buildHookMap(string $class): array
    {
        $map = [];
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $event = $this->attributeToEvent($attribute->getName());

                if ($event !== null) {
                    $map[$event][] = $method;
                }
            }
        }

        return $map;
    }

    private function attributeToEvent(string $attributeClass): ?string
    {
        return match ($attributeClass) {
            OnCreate::class => 'postPersist',
            OnUpdate::class => 'postUpdate',
            OnDelete::class => 'postRemove',
            OnFlush::class => 'onFlush',
            OnLoad::class => 'postLoad',
            default => null,
        };
    }
}
