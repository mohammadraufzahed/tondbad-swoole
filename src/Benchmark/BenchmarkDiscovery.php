<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

use ReflectionClass;
use ReflectionMethod;
use TondbadSwoole\Benchmark\Attributes\Benchmark as BenchmarkAttribute;
use TondbadSwoole\Benchmark\Attributes\Param;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Attributes\Teardown;

/**
 * Discovers benchmark scenarios from class files in a directory.
 */
final class BenchmarkDiscovery
{
    /**
     * @param list<string> $directories
     */
    public function __construct(
        private readonly array $directories,
    ) {
    }

    /**
     * @return list<Scenario>
     */
    public function discover(): array
    {
        $scenarios = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . '/*.php') ?: [] as $file) {
                require_once $file;
            }

            foreach (get_declared_classes() as $class) {
                $reflection = new ReflectionClass($class);

                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                    continue;
                }

                $classAttributes = $reflection->getAttributes(BenchmarkAttribute::class);
                $classAttribute = $classAttributes[0] ?? null;

                if ($classAttribute === null && !$this->hasBenchmarkMethod($reflection)) {
                    continue;
                }

                $classConfig = $classAttribute?->newInstance();
                $setupMethod = $this->findAttributedMethod($reflection, Setup::class);
                $teardownMethod = $this->findAttributedMethod($reflection, Teardown::class);

                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->isStatic() || $method->isAbstract() || $method->isConstructor()) {
                        continue;
                    }

                    if ($method->getName() === $setupMethod || $method->getName() === $teardownMethod) {
                        continue;
                    }

                    $methodAttributes = $method->getAttributes(BenchmarkAttribute::class);

                    if ($classConfig === null && $methodAttributes === []) {
                        continue;
                    }

                    $methodConfig = $methodAttributes[0] ?? null;
                    $config = $methodConfig?->newInstance() ?? $classConfig;

                    if ($config === null) {
                        continue;
                    }

                    $params = $this->resolveParams($reflection);

                    foreach ($this->expandParams($params) as $paramSet) {
                        $scenarios[] = $this->buildScenario(
                            $reflection,
                            $method,
                            $config,
                            $paramSet,
                            $setupMethod,
                            $teardownMethod,
                        );
                    }
                }
            }
        }

        return $scenarios;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function resolveParams(ReflectionClass $class): array
    {
        $params = [];

        foreach ($class->getProperties() as $property) {
            $attributes = $property->getAttributes(Param::class);

            if ($attributes === []) {
                continue;
            }

            /** @var Param $param */
            $param = $attributes[0]->newInstance();
            $params[$property->getName()] = $param->values;
        }

        return $params;
    }

    /**
     * @param array<string, list<mixed>> $params
     * @return list<array<string, mixed>>
     */
    private function expandParams(array $params): array
    {
        if ($params === []) {
            return [[]];
        }

        $keys = array_keys($params);
        $values = array_values($params);

        $combinations = [[]];

        foreach ($values as $index => $list) {
            $new = [];

            foreach ($combinations as $combo) {
                foreach ($list as $value) {
                    $new[] = [...$combo, $keys[$index] => $value];
                }
            }

            $combinations = $new;
        }

        return $combinations;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildScenario(
        ReflectionClass $class,
        ReflectionMethod $method,
        BenchmarkAttribute $config,
        array $params,
        ?string $setupMethod,
        ?string $teardownMethod,
    ): Scenario {
        $paramSuffix = $params === [] ? '' : ' [' . $this->formatParams($params) . ']';

        return new Scenario(
            name: $config->name ?? $class->getShortName() . '::' . $method->getName() . $paramSuffix,
            group: $config->group ?? $class->getNamespaceName(),
            params: $params,
            warmup: $config->warmup,
            iterations: $config->iterations,
            invocations: $config->invocations,
            forks: $config->forks,
            mode: $config->mode,
            timeUnit: $config->timeUnit,
            class: $class->getName(),
            method: $method->getName(),
            setupMethod: $setupMethod,
            teardownMethod: $teardownMethod,
            file: $class->getFileName(),
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function formatParams(array $params): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . (is_string($value) ? $value : json_encode($value));
        }

        return implode(', ', $parts);
    }

    private function hasBenchmarkMethod(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(BenchmarkAttribute::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<object> $attribute
     */
    private function findAttributedMethod(ReflectionClass $class, string $attribute): ?string
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes($attribute) !== []) {
                return $method->getName();
            }
        }

        return null;
    }
}
