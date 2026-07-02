<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => $this->faker->company(),
            'type' => 'company',
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'DE', 'FR', 'JP', 'AU']),
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        // Every client has a default legal entity.
        return $this->afterCreating(function (\App\Models\Client $client) {
            if (! $client->entities()->exists()) {
                $client->entities()->create([
                    'name' => $client->name,
                    'country_code' => $client->country_code,
                    'billing_email' => $client->email,
                    'is_default' => true,
                ]);
            }
        });
    }
}
