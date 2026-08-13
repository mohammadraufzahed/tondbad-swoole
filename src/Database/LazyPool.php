<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use OpenSwoole\Coroutine\Channel;

class LazyPool implements PoolInterface
{
    private ?PoolInterface $pool = null;

    /**
     * @param \Closure(): \PDO $factory
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly \Closure $factory,
        private readonly int $min = 0,
        private readonly int $max = 10,
        private readonly float $waitTimeout = 3.0,
        private readonly array $config = [],
    ) {
    }

    public function get(): mixed
    {
        $this->init();

        return $this->pool->get();
    }

    public function put(mixed $resource): void
    {
        $this->pool?->put($resource);
    }

    public function close(): void
    {
        $this->pool?->close();
        $this->pool = null;
    }

    public function stats(): array
    {
        if ($this->pool !== null) {
            return $this->pool->stats();
        }

        return [
            'total' => 0,
            'available' => 0,
            'borrowed' => 0,
            'max' => $this->max,
            'min' => $this->min,
        ];
    }

    private function init(): void
    {
        if ($this->pool !== null) {
            return;
        }

        if ($this->shouldUseSwoolePool()) {
            $this->pool = new SwoolePdoPool(
                $this->factory,
                $this->min,
                $this->max,
                $this->waitTimeout,
                $this->config,
            );

            return;
        }

        $this->pool = new SimplePdoPool($this->factory, $this->config);
    }

    private function shouldUseSwoolePool(): bool
    {
        return $this->max > 1
            && class_exists(Channel::class)
            && $this->inCoroutine();
    }

    private function inCoroutine(): bool
    {
        return class_exists(\OpenSwoole\Coroutine::class)
            && \OpenSwoole\Coroutine::getCid() >= 0;
    }
}
