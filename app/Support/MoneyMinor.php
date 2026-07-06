<?php

namespace App\Support;

/**
 * Fixed-point money: every monetary column holds integer minor units
 * (hundredths). These helpers are the only place the ×100 lives.
 * Database aggregates (SUM/withSum) return minor units — convert them
 * with fromMinor() at the query site; model attributes convert
 * automatically through the Money cast.
 */
class MoneyMinor
{
    /** Minor-unit integer for a major-unit value: 12.34 → 1234. */
    public static function fromMajor(float|int|string $major): int
    {
        return (int) round((float) $major * 100);
    }

    /** Major-unit float for a minor-unit value: 1234 → 12.34. */
    public static function toMajor(int|float|string|null $minor): float
    {
        return round(((float) ($minor ?? 0)) / 100, 2);
    }

    /** Alias reading naturally at aggregate call sites. */
    public static function fromMinor(int|float|string|null $minor): float
    {
        return self::toMajor($minor);
    }
}
