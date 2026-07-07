<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'title' => ucfirst($this->faker->words(4, true)),
            'description' => null,
            'offset_value' => $this->faker->numberBetween(1, 12),
            'offset_unit' => 'months',
            'is_critical' => false,
            'sort_order' => 0,
        ];
    }
}
