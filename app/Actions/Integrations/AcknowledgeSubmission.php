<?php

namespace App\Actions\Integrations;

use App\Enums\SubmissionStatus;
use App\Enums\TaskStatus;
use App\Models\OfficeSubmission;

/**
 * The office has receipted a submission: store its reference and
 * complete the docket task the submission discharges.
 *
 * @return list<string> what was done, for audit logs
 */
class AcknowledgeSubmission
{
    public function handle(OfficeSubmission $submission, ?string $externalRef, ?array $receipt): array
    {
        $log = [];

        $submission->update([
            'status' => SubmissionStatus::Acknowledged,
            'external_ref' => $externalRef,
            'receipt' => $receipt,
            'acknowledged_at' => now(),
            'error' => null,
        ]);
        $log[] = sprintf(
            'Acknowledged submission #%d (%s)%s',
            $submission->id,
            $submission->submission_type->label(),
            $externalRef ? " — office ref {$externalRef}" : ''
        );

        $task = $submission->task;
        if ($task && in_array($task->status, [TaskStatus::Pending, TaskStatus::InProgress], true)) {
            $task->update(['status' => TaskStatus::Completed, 'completed_at' => now()]);
            $log[] = "Completed task “{$task->title}”";
        }

        return $log;
    }
}
