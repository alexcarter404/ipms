<?php

namespace Database\Factories;

use App\Enums\TriggerEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Workflow>
 */
class WorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->words(3, true)),
            'matter_type' => null,
            'trigger_event' => TriggerEvent::Filing,
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
