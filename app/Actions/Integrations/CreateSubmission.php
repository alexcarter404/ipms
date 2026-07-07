<?php

namespace App\Actions\Integrations;

use App\Enums\SubmissionType;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\OfficeSubmission;
use App\Models\User;
use App\Services\Integrations\BuildSubmissionPayload;

class CreateSubmission
{
    public function __construct(private BuildSubmissionPayload $builder) {}

    public function handle(Matter $matter, User $creator, array $data): OfficeSubmission
    {
        $type = SubmissionType::from($data['submission_type']);
        $task = isset($data['task_id'])
            ? MatterTask::where('matter_id', $matter->id)->findOrFail($data['task_id'])
            : null;

        return OfficeSubmission::create([
            'office' => $data['office'],
            'matter_id' => $matter->id,
            'task_id' => $task?->id,
            'submission_type' => $type,
            'payload' => $this->builder->handle($matter, $type, $task, $data['notes'] ?? null),
            'notes' => $data['notes'] ?? null,
            'created_by' => $creator->id,
        ]);
    }
}
