<?php

namespace App\Http\Controllers;

use App\Actions\Integrations\ProcessOfficeMessage;
use App\Enums\OfficeEventType;
use App\Enums\OfficeMessageStatus;
use App\Exceptions\DomainActionException;
use App\Enums\SubmissionType;
use App\Models\OfficeMessage;
use App\Models\OfficeSubmission;
use App\Repositories\MatterRepository;
use App\Services\Integrations\IngestOfficeMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeMessageController extends Controller
{
    public function index(Request $request, MatterRepository $matters): Response
    {
        $filters = $request->only('status', 'office');

        $messages = OfficeMessage::query()
            ->with('matter:id,reference,title')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['office'] ?? null, fn ($q, $office) => $q->where('office', $office))
            ->orderByRaw("case when status = 'needs_review' then 0 else 1 end")
            ->orderByDesc('received_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (OfficeMessage $message) => [
                'id' => $message->id,
                'office' => $message->office,
                'office_name' => $message->officeName(),
                'event_type' => $message->event_type->value,
                'event_label' => $message->event_type->label(),
                'application_no' => $message->application_no,
                'registration_no' => $message->registration_no,
                'event_date' => $message->event_date?->toDateString(),
                'summary' => $message->summary,
                'payload' => $message->payload,
                'matter' => $message->matter,
                'status' => $message->status->value,
                'actions' => $message->actions,
                'error' => $message->error,
                'received_at' => $message->received_at->toDateTimeString(),
                'processed_at' => $message->processed_at?->toDateTimeString(),
            ]);

        return Inertia::render('Integrations/Index', [
            'messages' => $messages,
            'filters' => $filters,
            'statuses' => OfficeMessageStatus::options(),
            'eventTypes' => OfficeEventType::options(),
            'offices' => collect(config('integrations.offices'))
                ->map(fn ($config, $code) => [
                    'value' => $code,
                    'label' => $config['name'],
                    'driver' => $config['driver'],
                ])->values(),
            'counts' => [
                'needs_review' => OfficeMessage::where('status', OfficeMessageStatus::NeedsReview)->count(),
                'processed' => OfficeMessage::where('status', OfficeMessageStatus::Processed)->count(),
            ],
            'matterOptions' => $matters->referenceOptions(),
            'submissions' => OfficeSubmission::with(['matter:id,reference', 'task:id,title', 'creator:id,name'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (OfficeSubmission $submission) => [
                    'id' => $submission->id,
                    'office' => $submission->office,
                    'office_name' => $submission->officeName(),
                    'type' => $submission->submission_type->value,
                    'type_label' => $submission->submission_type->label(),
                    'matter' => $submission->matter,
                    'task' => $submission->task,
                    'payload' => $submission->payload,
                    'status' => $submission->status->value,
                    'external_ref' => $submission->external_ref,
                    'error' => $submission->error,
                    'created_by' => $submission->creator?->name,
                    'created_at' => $submission->created_at->toDateTimeString(),
                    'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                    'acknowledged_at' => $submission->acknowledged_at?->toDateTimeString(),
                ]),
            'submissionTypes' => SubmissionType::options(),
            'openTasks' => \App\Models\MatterTask::whereIn('status', ['pending', 'in_progress'])
                ->get(['id', 'matter_id', 'title'])
                ->groupBy('matter_id')
                ->map(fn ($tasks) => $tasks->map(fn ($t) => ['value' => $t->id, 'label' => $t->title])->values()),
        ]);
    }

    public function poll(IngestOfficeMessages $ingest): RedirectResponse
    {
        $stats = $ingest->pollAll();

        return back()->with('success', sprintf(
            'Polled all offices — %d new message(s), %d auto-processed, %d awaiting review.',
            $stats['ingested'], $stats['processed'], $stats['review']
        ));
    }

    /** Manually point an unmatched message at a matter. */
    public function assign(Request $request, OfficeMessage $officeMessage): RedirectResponse
    {
        $data = $request->validate(['matter_id' => ['required', 'exists:matters,id']]);

        if ($officeMessage->status === OfficeMessageStatus::Processed) {
            return back()->with('error', 'Processed messages cannot be reassigned.');
        }

        $officeMessage->update([
            'matter_id' => $data['matter_id'],
            'status' => OfficeMessageStatus::Matched,
            'error' => null,
        ]);

        return back()->with('success', 'Message assigned — ready to process.');
    }

    public function process(OfficeMessage $officeMessage, ProcessOfficeMessage $action): RedirectResponse
    {
        try {
            $action->handle($officeMessage);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Processed — %d action(s) applied to %s.',
            count($officeMessage->fresh()->actions ?? []),
            $officeMessage->matter?->reference
        ));
    }

    public function dismiss(OfficeMessage $officeMessage): RedirectResponse
    {
        if ($officeMessage->status === OfficeMessageStatus::Processed) {
            return back()->with('error', 'Processed messages cannot be dismissed.');
        }

        $officeMessage->update(['status' => OfficeMessageStatus::Dismissed]);

        return back()->with('success', 'Message dismissed.');
    }
}
