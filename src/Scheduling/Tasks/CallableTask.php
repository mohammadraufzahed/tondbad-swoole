<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Tasks;

use InvalidArgumentException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class CallableTask implements Task
{
    /**
     * @var array{0: class-string|object, 1: string}|string
     */
    private readonly array|string $callable;

    public function __construct(array|string $callable)
    {
        if (is_string($callable)) {
            $callable = $this->parseString($callable);
        }

        if (!is_array($callable) || count($callable) !== 2) {
            throw new InvalidArgumentException('Callable must be an array [class, method] or string Class::method.');
        }

        $this->callable = $callable;
    }

    public function execute(Container $container, string $basePath, ?ScheduleRegistry $registry = null): mixed
    {
        $callable = $this->callable;

        if (is_array($callable) && is_string($callable[0])) {
            $callable[0] = $container->make($callable[0]);
        }

        return $container->call($callable);
    }

    public function toArray(): array
    {
        $first = is_object($this->callable[0]) ? get_class($this->callable[0]) : $this->callable[0];

        return [
            'type' => 'callable',
            'callable' => [$first, $this->callable[1]],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseString(string $callable): array
    {
        if (str_contains($callable, '::')) {
            [$class, $method] = explode('::', $callable, 2);

            return [$class, $method];
        }

        if (str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);

            return [$class, $method];
        }

        throw new InvalidArgumentException("Invalid callable string: {$callable}");
    }
}
