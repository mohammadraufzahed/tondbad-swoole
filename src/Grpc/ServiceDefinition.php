<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class ServiceDefinition
{
    /** @param MethodDescriptor[] $methods */
    public function __construct(
        public readonly string $name,
        public readonly array $methods,
        public readonly ?string $package = null,
    ) {
    }

    public function getMethod(string $methodName): ?MethodDescriptor
    {
        foreach ($this->methods as $method) {
            if ($method->name === $methodName) {
                return $method;
            }
        }

        return null;
    }
}
