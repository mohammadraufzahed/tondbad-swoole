<?php

namespace TondbadSwoole\Core;

use Closure;

class Pipeline
{
    protected array $pipes = [];
    protected mixed $passable;
    protected ?Closure $destination;

    public function __construct(mixed $passable)
    {
        $this->passable = $passable;
    }

    /**
     * Set the array of pipes
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Set the final destination closure
     */
    public function then(Closure $destination): mixed
    {
        $this->destination = $destination;
        return $this->processPipeline();
    }

    /**
     * Process the pipeline
     */
    protected function processPipeline(): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->destination ?: fn($passable) => $passable
        );

        return $pipeline($this->passable);
    }

    /**
     * Get a closure that represents a single pipe segment
     */
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_callable($pipe)) {
                    return $pipe($passable, $stack);
                }

                [$class, $method] = is_array($pipe) ? $pipe : [$pipe, 'handle'];
                $instance = new $class();
                return $instance->$method($passable, $stack);
            };
        };
    }
}

