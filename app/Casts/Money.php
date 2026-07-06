<?php

namespace App\Casts;

use App\Support\MoneyMinor;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Monetary values are stored as integer minor units (fixed-point,
 * 2 decimal places: 12.34 → 1234) so sums and comparisons in the
 * database are exact — no float/decimal rounding drift. The model
 * attribute stays in major units, so services, controllers and the UI
 * keep working with ordinary 12.34 values; the single rounding point
 * is here, on write.
 */
class Money implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null ? null : MoneyMinor::toMajor((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return MoneyMinor::fromMajor($value);
    }
}
