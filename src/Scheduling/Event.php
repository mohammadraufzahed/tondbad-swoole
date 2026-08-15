<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Scheduling\Contracts\Trigger;
use TondbadSwoole\Scheduling\Triggers\CronTrigger;
use TondbadSwoole\Scheduling\Tasks\ClosureTask;
use TondbadSwoole\Scheduling\Tasks\CommandTask;
use TondbadSwoole\Scheduling\Tasks\CallableTask;
use TondbadSwoole\Scheduling\Tasks\ExecTask;
use TondbadSwoole\Scheduling\Tasks\QueueTask;
use TondbadSwoole\Scheduling\Triggers\DelayTrigger;
use TondbadSwoole\Scheduling\Triggers\IntervalTrigger;
use TondbadSwoole\Scheduling\Triggers\OnceTrigger;

class Event
{
    public function __construct(
        private ScheduleDefinition $definition,
        private readonly Scheduler $scheduler,
        private readonly Container $container,
        private readonly string $basePath,
        private readonly ScheduleRegistry $registry,
    ) {
    }

    public function getDefinition(): ScheduleDefinition
    {
        return $this->definition;
    }

    public function cron(string $expression): self
    {
        return $this->withTrigger(CronTrigger::fromExpression($expression));
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyThreeMinutes(): self
    {
        return $this->cron('*/3 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): self
    {
        return $this->cron("0 {$firstHour},{$secondHour} * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $day, string $time = '00:00'): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron("{$minute} {$hour} * * {$day}");
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $day = 1, string $time = '00:00'): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    public function weekdays(): self
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function weekends(): self
    {
        return $this->cron('0 0 * * 0,6');
    }

    public function sundays(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function mondays(): self
    {
        return $this->cron('0 0 * * 1');
    }

    public function tuesdays(): self
    {
        return $this->cron('0 0 * * 2');
    }

    public function wednesdays(): self
    {
        return $this->cron('0 0 * * 3');
    }

    public function thursdays(): self
    {
        return $this->cron('0 0 * * 4');
    }

    public function fridays(): self
    {
        return $this->cron('0 0 * * 5');
    }

    public function saturdays(): self
    {
        return $this->cron('0 0 * * 6');
    }

    public function everySecond(): self
    {
        return $this->withTrigger(new IntervalTrigger(1));
    }

    public function everyFiveSeconds(): self
    {
        return $this->withTrigger(new IntervalTrigger(5));
    }

    public function everyTenSeconds(): self
    {
        return $this->withTrigger(new IntervalTrigger(10));
    }

    public function everySeconds(int $seconds): self
    {
        return $this->withTrigger(new IntervalTrigger($seconds));
    }

    public function once(): self
    {
        return $this->withTrigger(new OnceTrigger());
    }

    public function delay(int $seconds): self
    {
        return $this->withTrigger(new DelayTrigger($seconds));
    }

    public function timezone(string $timezone): self
    {
        $this->definition = $this->definition->withTimezone($timezone);
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function between(string $startTime, string $endTime): self
    {
        $this->definition = $this->definition->withBetween($startTime, $endTime, false);
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function unlessBetween(string $startTime, string $endTime): self
    {
        $this->definition = $this->definition->withBetween($startTime, $endTime, true);
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function withoutOverlapping(?int $lease = null): self
    {
        $this->definition->withoutOverlappingLease = $lease ?? 3600;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function runInBackground(): self
    {
        $this->definition->runInBackground = true;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function appendOutputTo(string $path): self
    {
        $this->definition->outputPath = $path;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function emailOutputTo(string $email): self
    {
        return $this;
    }

    public function description(string $description): self
    {
        $this->definition = $this->definition->withDescription($description);
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function name(string $description): self
    {
        return $this->description($description);
    }

    public function onQueue(string $queue): self
    {
        $this->definition->queue = $queue;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->definition->connection = $connection;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function retry(int $times, array $backoff = []): self
    {
        $this->definition->maxAttempts = $times;
        $this->definition->backoff = $backoff;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function throttle(int $maxAttempts, int $window = 60): self
    {
        $this->definition->rateLimitMax = $maxAttempts;
        $this->definition->rateLimitWindow = $window;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function misfire(string $policy): self
    {
        $this->definition->misfire = $policy;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function rateLimit(int $max, int $window): self
    {
        $this->definition->rateLimitMax = $max;
        $this->definition->rateLimitWindow = $window;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function from(DateTimeImmutable $date): self
    {
        $this->definition->startDate = $date;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function to(DateTimeImmutable $date): self
    {
        $this->definition->endDate = $date;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function tag(string ...$tags): self
    {
        $this->definition->tags = array_values(array_unique(array_merge($this->definition->tags, $tags)));
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function withData(array $data): self
    {
        $this->definition->data = $data;
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    public function isDue(DateTimeInterface $time): bool
    {
        return $this->scheduler->isDue($this->definition, $time);
    }

    public function getNextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        return $this->scheduler->getNextRunDate($this->definition, $from);
    }

    public function getExpression(): string
    {
        return $this->definition->getExpression();
    }

    public function getDescription(): string
    {
        return $this->definition->description ?? $this->definition->name;
    }

    public function run(Container $container, string $basePath): bool
    {
        return $this->scheduler->runDefinition($this->definition, new DateTimeImmutable(), skipDue: true);
    }

    private function withTrigger(Trigger $trigger): self
    {
        $this->definition = $this->definition->withTrigger($trigger);
        $this->scheduler->upsert($this->definition);

        return $this;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseTime(string $time): array
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            throw new InvalidArgumentException("Invalid time format: {$time}. Expected HH:MM.");
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidArgumentException("Invalid time value: {$time}.");
        }

        return [$hour, $minute];
    }
}
