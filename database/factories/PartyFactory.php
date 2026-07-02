<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Party>
 */
class PartyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'type' => 'individual',
            'email' => $this->faker->safeEmail(),
            'phone' => null,
            'address' => $this->faker->address(),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'DE', 'FR', 'JP']),
            'notes' => null,
        ];
    }

    public function organisation(): static
    {
        return $this->state(fn () => [
            'name' => $this->faker->company(),
            'type' => 'organisation',
        ]);
    }
}
