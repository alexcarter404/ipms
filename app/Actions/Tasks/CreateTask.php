<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\User;

class CreateTask
{
    public function handle(Matter $matter, array $data, User $creator): MatterTask
    {
        return $matter->tasks()->create($data + [
            'status' => TaskStatus::Pending,
            'created_by' => $creator->id,
        ]);
    }
}
