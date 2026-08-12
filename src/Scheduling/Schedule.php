<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\QueueInterface;

class Schedule
{
    /**
     * @var list<Event>
     */
    private array $events = [];

    public function __construct(
        private readonly Container $container,
        private readonly string $basePath,
    ) {
    }

    public function command(string $command, array $parameters = []): Event
    {
        $callback = function () use ($command, $parameters): void {
            $this->container->make(Application::class)->run(array_merge(['tondbad', $command], $parameters));
        };

        return $this->addEvent(new Event($command, $callback));
    }

    public function call(callable $callback): Event
    {
        $callable = $callback;

        if (is_array($callback) && is_string($callback[0])) {
            $callable = [$this->container->make($callback[0]), $callback[1]];
        }

        $description = match (true) {
            is_string($callback) && str_contains($callback, '::') => $callback,
            is_array($callback) && is_object($callback[0]) => get_class($callback[0]) . '@' . $callback[1],
            is_array($callback) && is_string($callback[0]) => $callback[0] . '::' . $callback[1],
            is_string($callback) => $callback,
            default => 'Closure',
        };

        $closure = function () use ($callable): void {
            $this->container->call($callable);
        };

        return $this->addEvent(new Event($description, $closure));
    }

    public function job(Job $job, ?string $queue = null): Event
    {
        $description = $job::class;

        $callback = function () use ($job, $queue): void {
            $this->container->make(QueueInterface::class)->push($job, $queue);
        };

        return $this->addEvent(new Event($description, $callback));
    }

    public function exec(string $command, array $parameters = []): Event
    {
        $callback = function () use ($command, $parameters): void {
            $line = $command;

            foreach ($parameters as $parameter) {
                $line .= ' ' . escapeshellarg((string) $parameter);
            }

            passthru($line, $code);

            if ($code !== 0) {
                throw new \RuntimeException("Scheduled exec returned exit code {$code}: {$line}");
            }
        };

        return $this->addEvent(new Event($command, $callback));
    }

    public function addEvent(Event $event): Event
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<Event>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<Event>
     */
    public function dueEvents(DateTimeInterface $time): array
    {
        $due = [];

        foreach ($this->events as $event) {
            if ($event->isDue($time)) {
                $due[] = $event;
            }
        }

        return $due;
    }

    public function runDueEvents(DateTimeInterface $time): int
    {
        $ran = 0;

        foreach ($this->dueEvents($time) as $event) {
            if ($event->run($this->container, $this->basePath)) {
                $ran++;
            }
        }

        return $ran;
    }

    public function getNextRunDate(DateTimeInterface $from): ?DateTimeImmutable
    {
        $next = null;

        foreach ($this->events as $event) {
            $candidate = $event->getNextRunDate($from);

            if ($next === null || $candidate < $next) {
                $next = $candidate;
            }
        }

        return $next;
    }
}
