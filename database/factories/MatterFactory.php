<?php

namespace Database\Factories;

use App\Enums\MatterStatus;
use App\Enums\MatterType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Matter>
 */
class MatterFactory extends Factory
{
    public function definition(): array
    {
        $filed = $this->faker->dateTimeBetween('-6 years', '-1 month');

        return [
            'reference' => 'P-'.$this->faker->unique()->numerify('####-####'),
            'matter_type' => MatterType::Patent,
            'title' => ucfirst($this->faker->bs()),
            'client_id' => Client::factory(),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'DE', 'EP', 'JP', 'CN', 'AU']),
            'filing_route' => 'national',
            'status' => MatterStatus::Filed,
            'application_no' => $this->faker->numerify('##/###,###'),
            'application_date' => $filed,
            'description' => $this->faker->sentence(12),
        ];
    }

    public function trademark(): static
    {
        return $this->state(fn () => [
            'reference' => 'TM-'.$this->faker->unique()->numerify('####-####'),
            'matter_type' => MatterType::Trademark,
            'title' => strtoupper($this->faker->word()),
        ]);
    }

    public function design(): static
    {
        return $this->state(fn () => [
            'reference' => 'D-'.$this->faker->unique()->numerify('####-####'),
            'matter_type' => MatterType::Design,
        ]);
    }

    public function granted(): static
    {
        return $this->state(function (array $attributes) {
            $filed = $attributes['application_date'] ?? $this->faker->dateTimeBetween('-8 years', '-3 years');

            return [
                'status' => MatterStatus::Granted,
                'registration_no' => $this->faker->numerify('#,###,###'),
                'registration_date' => $this->faker->dateTimeBetween($filed, 'now'),
            ];
        });
    }
}
