<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Applies a workflow template to a matter: each step becomes a task with
 * a due date offset from the trigger's base date.
 */
class WorkflowRunner
{
    /**
     * @return Collection<int, MatterTask>
     */
    public function apply(Workflow $workflow, Matter $matter, CarbonInterface $baseDate, ?User $actor = null, ?int $assigneeId = null, ?WorkflowStep $startAt = null): Collection
    {
        $created = collect();

        $steps = $startAt
            ? $workflow->steps->filter(fn (WorkflowStep $step) => $step->sort_order >= $startAt->sort_order)
            : $workflow->steps;

        foreach ($steps as $step) {
            $created->push($matter->tasks()->create([
                'workflow_step_id' => $step->id,
                'title' => $step->title,
                'description' => $step->description,
                'due_date' => $step->dueDateFrom($baseDate),
                'is_critical' => $step->is_critical,
                'priority' => $step->is_critical ? TaskPriority::Critical : TaskPriority::Normal,
                'status' => TaskStatus::Pending,
                'assigned_to' => $assigneeId ?? $matter->responsible_user_id,
                'created_by' => $actor?->id,
            ]));
        }

        return $created;
    }
}
