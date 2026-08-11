<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

class CronExpression
{
    private string $expression;

    /**
     * @var array<int, list<int>>
     */
    private array $fields = [];

    private const MACROS = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@midnight' => '0 0 * * *',
        '@hourly' => '0 * * * *',
    ];

    public function __construct(string $expression)
    {
        $this->expression = $this->normalize($expression);
        $this->fields = [
            $this->parseField($this->expression, 0, [0, 59]),
            $this->parseField($this->expression, 1, [0, 23]),
            $this->parseField($this->expression, 2, [1, 31]),
            $this->parseField($this->expression, 3, [1, 12]),
            $this->parseField($this->expression, 4, [0, 6]),
        ];
    }

    public static function make(string $expression): self
    {
        return new self($expression);
    }

    private function normalize(string $expression): string
    {
        $expression = trim($expression);

        if (str_starts_with($expression, '@')) {
            if (!isset(self::MACROS[$expression])) {
                throw new InvalidArgumentException("Unsupported cron macro: {$expression}");
            }

            return self::MACROS[$expression];
        }

        $parts = preg_split('/\s+/', $expression);

        if ($parts === false || count($parts) < 5) {
            throw new InvalidArgumentException("Invalid cron expression: {$expression}");
        }

        return implode(' ', array_slice($parts, 0, 5));
    }

    /**
     * @return list<int>
     */
    private function parseField(string $expression, int $index, array $range): array
    {
        $parts = explode(' ', $expression);
        $field = $parts[$index] ?? '*';
        $values = [];

        foreach (explode(',', $field) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || $segment === '*' || $segment === '?') {
                $segment = '*';
            }

            $step = 1;
            $rangePart = $segment;

            if (str_contains($segment, '/')) {
                [$rangePart, $step] = explode('/', $segment, 2);
                $step = (int) $step;

                if ($step < 1) {
                    $step = 1;
                }
            }

            if ($rangePart === '*' || $rangePart === '?') {
                $start = $range[0];
                $end = $range[1];
            } elseif (str_contains($rangePart, '-')) {
                [$start, $end] = explode('-', $rangePart, 2);
                $start = (int) $start;
                $end = (int) $end;
            } else {
                $start = (int) $rangePart;
                $end = $start;
            }

            for ($i = $start; $i <= $end; $i += $step) {
                $value = $i;

                if ($index === 4 && $value === 7) {
                    $value = 0;
                }

                if ($value >= $range[0] && $value <= $range[1]) {
                    $values[$value] = true;
                }
            }
        }

        $result = array_keys($values);
        sort($result);

        return $result;
    }

    public function isDue(DateTimeInterface $time): bool
    {
        $date = DateTimeImmutable::createFromInterface($time);

        $checks = [
            (int) $date->format('i'),
            (int) $date->format('G'),
            (int) $date->format('j'),
            (int) $date->format('n'),
            (int) $date->format('w'),
        ];

        foreach ($checks as $index => $value) {
            if (!in_array($value, $this->fields[$index], true)) {
                return false;
            }
        }

        return true;
    }

    public function getNextRunDate(DateTimeInterface $from, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromInterface($from);

        if ($timezone !== null) {
            $date = $date->setTimezone($timezone);
        }

        if ($this->isDue($date)) {
            return $date;
        }

        $candidate = $date->setTime((int) $date->format('G'), (int) $date->format('i'), 0, 0);

        for ($i = 0; $i < 366 * 24 * 60; $i++) {
            $candidate = $candidate->modify('+1 minute');

            if ($this->isDue($candidate)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Unable to find next run date for expression: ' . $this->expression);
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}
