<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MatterTask>
 */
class MatterTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'title' => ucfirst($this->faker->words(4, true)),
            'description' => $this->faker->optional()->sentence(),
            'due_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Pending,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => $this->faker->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }
}
