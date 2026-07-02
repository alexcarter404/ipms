<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\MatterTask;
use App\Models\User;

class UpdateTask
{
    /** Completing a task records who completed it and when. */
    public function handle(MatterTask $task, array $data, User $actor): MatterTask
    {
        if (($data['status'] ?? null) === TaskStatus::Completed->value && $task->status !== TaskStatus::Completed) {
            $data['completed_at'] = now();
            $data['completed_by'] = $actor->id;
        }

        $task->update($data);

        return $task;
    }
}
