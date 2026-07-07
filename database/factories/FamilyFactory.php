<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
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
