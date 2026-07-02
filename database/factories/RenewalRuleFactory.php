<?php

namespace Database\Factories;

use App\Enums\MatterType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RenewalRule>
 */
class RenewalRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Patent Annuities',
            'matter_type' => MatterType::Patent,
            'country_code' => null,
            'base_date' => 'application',
            'start_cycle' => 2,
            'end_cycle' => 20,
            'interval_years' => 1,
            'offsets_months' => null,
            'grace_months' => 6,
            'is_active' => true,
        ];
    }
}
