<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Tasks;

use Closure;
use InvalidArgumentException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class ClosureTask implements Task
{
    private ?Closure $closure = null;

    public function __construct(
        private readonly string $closureId,
        ?Closure $closure = null,
    ) {
        $this->closure = $closure;
    }

    public static function fromClosure(Closure $closure, ScheduleRegistry $registry, ?string $closureId = null): self
    {
        $id = $closureId ?? uniqid('closure_', true);
        $registry->register($id, $closure);

        return new self($id, $closure);
    }

    public function getClosureId(): string
    {
        return $this->closureId;
    }

    public function execute(Container $container, string $basePath, ?ScheduleRegistry $registry = null): mixed
    {
        $closure = $this->closure ?? $registry?->resolve($this->closureId);

        if ($closure === null) {
            throw new InvalidArgumentException("Closure not found for schedule task: {$this->closureId}");
        }

        return $container->call($closure);
    }

    public function toArray(): array
    {
        return [
            'type' => 'closure',
            'closureId' => $this->closureId,
        ];
    }
}
