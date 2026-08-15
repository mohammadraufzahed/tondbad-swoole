<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

enum Mode: string
{
    case Throughput = 'throughput';
    case AverageTime = 'avg';
    case SampleTime = 'sample';
    case SingleShotTime = 'single';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'throughput', 't' => self::Throughput,
            'avg', 'averagetime', 'average' => self::AverageTime,
            'sample', 'sampletime' => self::SampleTime,
            'single', 'singleshot', 'singleshottime' => self::SingleShotTime,
            default => self::AverageTime,
        };
    }
}
