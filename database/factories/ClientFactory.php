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
            'address' => $this->faker->address(),
            'country_code' => $this->faker->randomElement(['US', 'GB', 'DE', 'FR', 'JP', 'AU']),
            'vat_number' => null,
            'notes' => null,
        ];
    }
}
