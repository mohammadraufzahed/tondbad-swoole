<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Pipeline;

use Closure;
use InvalidArgumentException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Pipeline\Contracts\PipeInterface;

class Pipeline
{
    private mixed $passable;

    /**
     * @var array<int, mixed>
     */
    private array $pipes = [];

    private string $method = 'handle';

    public function __construct(private readonly Container $container)
    {
    }

    public static function send(mixed $passable, ?Container $container = null): self
    {
        $pipeline = new self($container ?? new Container());
        $pipeline->passable = $passable;

        return $pipeline;
    }

    /**
     * @param array<int, mixed> $pipes
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;

        return $this;
    }

    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            fn(mixed $passable) => $destination($passable)
        );

        return $pipeline($this->passable);
    }

    public function thenReturn(): mixed
    {
        return $this->then(fn(mixed $passable) => $passable);
    }

    private function carry(): Closure
    {
        return function (Closure $stack, mixed $pipe): Closure {
            return function (mixed $passable) use ($stack, $pipe): mixed {
                if (is_callable($pipe)) {
                    return $pipe($passable, $stack);
                }

                if ($pipe instanceof PipeInterface) {
                    return $pipe->handle($passable, $stack);
                }

                if (is_object($pipe) && method_exists($pipe, $this->method)) {
                    return $pipe->{$this->method}($passable, $stack);
                }

                if (is_string($pipe) && class_exists($pipe)) {
                    $pipeInstance = $this->container->make($pipe);

                    if (!method_exists($pipeInstance, $this->method)) {
                        throw new InvalidArgumentException("Pipe class [{$pipe}] does not have a [{$this->method}] method.");
                    }

                    return $pipeInstance->{$this->method}($passable, $stack);
                }

                throw new InvalidArgumentException('Invalid pipe type.');
            };
        };
    }
}
