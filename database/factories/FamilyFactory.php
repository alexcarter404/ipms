<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Family>
 */
class FamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'FAM-'.$this->faker->unique()->numerify('####'),
            'name' => ucfirst($this->faker->words(3, true)),
            'notes' => null,
        ];
    }
}
