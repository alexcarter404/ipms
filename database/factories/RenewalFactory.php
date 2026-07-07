<?php

namespace Database\Factories;

use App\Enums\RenewalStatus;
use App\Models\Matter;
use App\Models\Renewal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Renewal>
 */
class RenewalFactory extends Factory
{
    public function definition(): array
    {
        $due = $this->faker->dateTimeBetween('now', '+1 year');

        return [
            'matter_id' => Matter::factory(),
            'cycle' => $this->faker->unique()->numberBetween(1, 20),
            'due_date' => $due,
            'grace_date' => (clone $due)->modify('+6 months'),
            'status' => RenewalStatus::Upcoming,
            'official_fee' => $this->faker->randomFloat(2, 100, 2000),
            'service_fee' => $this->faker->randomFloat(2, 50, 400),
            'currency' => 'USD',
        ];
    }
}
