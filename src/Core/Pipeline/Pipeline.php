<?php

namespace TondbadSwoole\Core\Pipeline;

use Closure;
use InvalidArgumentException;
use TondbadSwoole\Core\Container;

class Pipeline
{
    protected $passable;
    protected $pipes = [];
    protected $method = 'handle';
    private readonly Container $container;

    public function __construct()
    {
        $this->container = Container::create();
    }

    public static function send($passable): self
    {
        $pipeline = new static;
        $pipeline->passable = $passable;
        return $pipeline;
    }

    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function then(Closure $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            fn($passable) => $destination($passable)
        );

        return $pipeline($this->passable);
    }

    public function thenReturn()
    {
        return $this->then(fn($passable) => $passable);
    }

    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_callable($pipe)) {
                    return $pipe($passable, $stack);
                }

                if (is_object($pipe)) {
                    return $pipe->{$this->method}($passable, $stack);
                }

                if (is_string($pipe) && class_exists($pipe)) {
                    $pipeInstance = $this->container->make($pipe);
                    return $pipeInstance->{$this->method}($passable, $stack);
                }

                throw new InvalidArgumentException('Invalid pipe type.');
            };
        };
    }
}
