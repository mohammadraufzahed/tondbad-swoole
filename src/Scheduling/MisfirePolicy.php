<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

enum MisfirePolicy: string
{
    case FIRE_ONCE = 'fire_once';
    case FIRE_AND_PROCEED = 'fire_and_proceed';
    case IGNORE = 'ignore';
    case SMART = 'smart';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::SMART;
    }
}
