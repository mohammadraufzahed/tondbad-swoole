<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Monolog\Logger;
use Throwable;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;

class Event
{
    private CronExpression $cron;
    private ?DateTimeZone $timezone = null;
    private ?string $betweenStart = null;
    private ?string $betweenEnd = null;
    private bool $unlessBetween = false;
    private bool $withoutOverlapping = false;
    private bool $runInBackground = false;
    private ?string $outputPath = null;
    private ?string $description = null;

    /**
     * @var resource|false
     */
    private mixed $lockHandle = false;

    public function __construct(
        private readonly string $name,
        private readonly Closure $callback,
    ) {
        $this->cron = new CronExpression('* * * * *');
    }

    public function cron(string $expression): self
    {
        $this->cron = new CronExpression($expression);

        return $this;
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

    public function timezone(string $timezone): self
    {
        $this->timezone = new DateTimeZone($timezone);

        return $this;
    }

    public function between(string $startTime, string $endTime): self
    {
        $this->parseTime($startTime);
        $this->parseTime($endTime);

        $this->betweenStart = $startTime;
        $this->betweenEnd = $endTime;
        $this->unlessBetween = false;

        return $this;
    }

    public function unlessBetween(string $startTime, string $endTime): self
    {
        $this->between($startTime, $endTime);
        $this->unlessBetween = true;

        return $this;
    }

    public function withoutOverlapping(): self
    {
        $this->withoutOverlapping = true;

        return $this;
    }

    public function runInBackground(): self
    {
        $this->runInBackground = true;

        return $this;
    }

    public function appendOutputTo(string $path): self
    {
        $this->outputPath = $path;

        return $this;
    }

    public function emailOutputTo(string $email): self
    {
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isDue(DateTimeInterface $time): bool
    {
        $date = DateTimeImmutable::createFromInterface($time);

        if ($this->timezone !== null) {
            $date = $date->setTimezone($this->timezone);
        }

        $timeString = $date->format('H:i');

        if ($this->betweenStart !== null && $this->betweenEnd !== null) {
            $inBetween = $this->inBetweenWindow($timeString, $this->betweenStart, $this->betweenEnd);

            if ($this->unlessBetween && $inBetween) {
                return false;
            }

            if (!$this->unlessBetween && !$inBetween) {
                return false;
            }
        }

        return $this->cron->isDue($date);
    }

    public function getNextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        return $this->cron->getNextRunDate($from, $this->timezone);
    }

    public function getExpression(): string
    {
        return $this->cron->getExpression();
    }

    public function getDescription(): string
    {
        return $this->description ?? $this->name;
    }

    public function run(Container $container, string $basePath): bool
    {
        if ($this->withoutOverlapping && !$this->acquireLock($this->lockFile($container, $basePath))) {
            return false;
        }

        if ($this->outputPath !== null) {
            ob_start();
        }

        try {
            ($this->callback)();
        } catch (Throwable $e) {
            try {
                $logger = $container->make(Logger::class);
                $logger->error('Scheduled task failed: ' . $e->getMessage(), ['exception' => $e]);
            } catch (Exception) {
                fwrite(STDERR, 'Scheduled task failed: ' . $e->getMessage() . "\n");
            }
        } finally {
            if ($this->outputPath !== null) {
                $output = ob_get_clean();

                if ($output !== false && $output !== '') {
                    $this->ensureDirectory(dirname($this->outputPath));
                    file_put_contents($this->outputPath, $output, FILE_APPEND | LOCK_EX);
                }
            }

            if ($this->withoutOverlapping) {
                $this->releaseLock();
            }
        }

        return true;
    }

    public function runsInBackground(): bool
    {
        return $this->runInBackground;
    }

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

    private function inBetweenWindow(string $time, string $start, string $end): bool
    {
        [$startHour, $startMinute] = $this->parseTime($start);
        [$endHour, $endMinute] = $this->parseTime($end);

        $current = (int) str_replace(':', '', $time);
        $startValue = $startHour * 100 + $startMinute;
        $endValue = $endHour * 100 + $endMinute;

        if ($startValue <= $endValue) {
            return $current >= $startValue && $current <= $endValue;
        }

        return $current >= $startValue || $current <= $endValue;
    }

    private function lockFile(Container $container, string $basePath): string
    {
        $frameworkDir = $container->make(Config::class)->get('app.framework_cache_dir', $basePath . '/storage/framework');

        return $frameworkDir . '/schedule-' . md5($this->getDescription()) . '.lock';
    }

    private function acquireLock(string $lockFile): bool
    {
        $this->ensureDirectory(dirname($lockFile));

        $this->lockHandle = @fopen($lockFile, 'c');

        if ($this->lockHandle === false) {
            return false;
        }

        if (!flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($this->lockHandle);
            $this->lockHandle = false;

            return false;
        }

        return true;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = false;
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
