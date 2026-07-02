<?php

namespace App\Repositories;

use App\Enums\MatterType;
use App\Models\RenewalRule;
use Illuminate\Support\Collection;

class RenewalRuleRepository
{
    /** All rules, type-wide defaults listed before country overrides. */
    public function allOrdered(): Collection
    {
        return RenewalRule::query()
            ->orderBy('matter_type')
            ->orderByRaw('country_code is not null')
            ->orderBy('country_code')
            ->get();
    }

    /**
     * The rule governing a (type, country) pair: an exact country match
     * wins over the type-wide default (country_code = null).
     */
    public function resolveFor(MatterType $type, ?string $countryCode): ?RenewalRule
    {
        return RenewalRule::query()
            ->where('is_active', true)
            ->where('matter_type', $type)
            ->where(function ($q) use ($countryCode) {
                $q->whereNull('country_code');
                if ($countryCode) {
                    $q->orWhere('country_code', strtoupper($countryCode));
                }
            })
            ->orderByRaw('country_code is null')
            ->first();
    }
}
