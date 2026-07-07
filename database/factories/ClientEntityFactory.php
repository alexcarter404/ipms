<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientEntity>
 */
class ClientEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => $this->faker->company(),
            'registration_no' => $this->faker->numerify('########'),
            'vat_number' => 'GB'.$this->faker->numerify('#########'),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'DE', 'FR', 'JP']),
            'address' => $this->faker->address(),
            'billing_email' => $this->faker->companyEmail(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
