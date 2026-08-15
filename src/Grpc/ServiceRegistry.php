<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use TondbadSwoole\Core\Container;

final class ServiceRegistry
{
    /** @var array<string, ServiceDefinition> */
    private array $definitions = [];

    /** @var array<string, BindableService> */
    private array $services = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function add(string $serviceClass): void
    {
        if (!class_exists($serviceClass) || !is_subclass_of($serviceClass, BindableService::class)) {
            throw new \InvalidArgumentException("Class {$serviceClass} is not a BindableService.");
        }

        /** @var BindableService $service */
        $service = $this->container->make($serviceClass);
        $definition = $service->bindService();

        $this->services[$definition->name] = $service;
        $this->definitions[$definition->name] = $definition;
    }

    public function getDefinition(string $serviceName): ?ServiceDefinition
    {
        return $this->definitions[$this->normalize($serviceName)] ?? null;
    }

    public function getService(string $serviceName): ?BindableService
    {
        return $this->services[$this->normalize($serviceName)] ?? null;
    }

    /** @return array<string, ServiceDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    private function normalize(string $serviceName): string
    {
        return ltrim($serviceName, '/');
    }
}
