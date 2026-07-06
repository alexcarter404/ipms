<?php

namespace App\Http\Controllers;

use App\Actions\Integrations\CreateSubmission;
use App\Actions\Integrations\SubmitSubmission;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Exceptions\DomainActionException;
use App\Models\Matter;
use App\Models\OfficeSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeSubmissionController extends Controller
{
    public function store(Request $request, CreateSubmission $action): RedirectResponse
    {
        $data = $request->validate([
            'office' => ['required', Rule::in(array_keys(config('integrations.offices')))],
            'matter_id' => ['required', 'exists:matters,id'],
            'submission_type' => ['required', Rule::enum(SubmissionType::class)],
            'task_id' => ['nullable', 'exists:matter_tasks,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission = $action->handle(
            Matter::findOrFail($data['matter_id']),
            $request->user(),
            $data
        );

        return back()->with('success', sprintf(
            'Submission draft created — %s to %s for %s.',
            $submission->submission_type->label(),
            $submission->officeName(),
            $submission->matter->reference
        ));
    }

    public function submit(OfficeSubmission $officeSubmission, SubmitSubmission $action): RedirectResponse
    {
        try {
            $submission = $action->handle($officeSubmission);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $submission->status === SubmissionStatus::Acknowledged
            ? "Submitted and acknowledged — office ref {$submission->external_ref}."
            : 'Submitted to the exchange outbox — awaiting the office receipt.');
    }

    public function destroy(OfficeSubmission $officeSubmission): RedirectResponse
    {
        if ($officeSubmission->status !== SubmissionStatus::Draft) {
            return back()->with('error', 'Only draft submissions can be deleted.');
        }

        $officeSubmission->delete();

        return back()->with('success', 'Submission draft deleted.');
    }
}
