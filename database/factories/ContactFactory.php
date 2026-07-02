<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => 'person',
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'position' => $this->faker->jobTitle(),
            'is_primary' => false,
            'notes' => null,
        ];
    }

    public function mailbox(): static
    {
        return $this->state(fn () => [
            'type' => 'mailbox',
            'name' => 'IP Docketing',
            'email' => 'docketing@'.$this->faker->domainName(),
            'position' => null,
        ]);
    }
}
