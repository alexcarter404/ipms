<?php

namespace App\Actions\Integrations;

use App\Actions\Billing\AddDisbursement;
use App\Enums\OfficeEventType;
use App\Enums\OfficeMessageStatus;
use App\Enums\RenewalStatus;
use App\Enums\TaskStatus;
use App\Exceptions\DomainActionException;
use App\Models\CommTemplate;
use App\Models\Matter;
use App\Models\OfficeMessage;
use App\Models\OfficeSubmission;
use App\Models\Workflow;
use App\Services\TemplateRenderer;
use App\Services\WorkflowRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The automation pipeline for a matched office message. Everything it
 * does is appended to the message's actions log:
 *
 *  1. copy official numbers/dates from the payload onto the matter
 *  2. move the matter's status along (publication → published, …)
 *  3. auto-complete open tasks whose workflow step declares it is
 *     completed by this office event
 *  4. auto-apply active workflows triggered by this event (an office
 *     action fans out its response deadline chain)
 *  5. record official fees from the payload as disbursements at cost
 *  6. auto-draft configured communication templates for review
 */
class ProcessOfficeMessage
{
    public function __construct(
        private WorkflowRunner $workflows,
        private TemplateRenderer $renderer,
        private AddDisbursement $disbursements,
        private AcknowledgeSubmission $acknowledge,
        private \App\Actions\Documents\StoreDocument $documents,
    ) {
    }

    public function handle(OfficeMessage $message): OfficeMessage
    {
        if ($message->status === OfficeMessageStatus::Processed) {
            throw new DomainActionException('This message has already been processed.');
        }

        // Submission receipts correlate by submission id, not matter.
        if ($message->event_type === OfficeEventType::Receipt) {
            return $this->handleReceipt($message);
        }

        $matter = $message->matter;

        if (! $matter) {
            throw new DomainActionException('Assign a matter before processing this message.');
        }

        return DB::transaction(function () use ($message, $matter) {
            $log = [];
            $event = $message->event_type;
            $eventDate = $message->event_date ?? Carbon::today();

            $this->updateOfficialFields($message, $matter, $log);
            $this->updateStatus($event, $matter, $log);
            $this->completeTasks($event, $matter, $log);
            $this->applyWorkflows($event, $matter, $eventDate, $log);
            $this->addOfficialFees($message, $matter, $log);
            $this->fileDocuments($message, $matter, $log);
            $this->draftCommunications($event, $matter, $log);

            if ($event === OfficeEventType::RenewalReminder) {
                $renewal = $matter->renewals()
                    ->whereIn('status', [RenewalStatus::Upcoming])
                    ->orderBy('due_date')->first();
                if ($renewal) {
                    $renewal->update(['status' => RenewalStatus::ReminderSent]);
                    $log[] = "Marked renewal due {$renewal->due_date->toDateString()} as reminder sent";
                }
            }

            $message->update([
                'status' => OfficeMessageStatus::Processed,
                'actions' => $log ?: ['No automated actions applied'],
                'processed_at' => now(),
                'error' => null,
            ]);

            return $message;
        });
    }

    /** An office receipt for one of our outbound submissions. */
    private function handleReceipt(OfficeMessage $message): OfficeMessage
    {
        $submission = OfficeSubmission::find($message->payload['submission_id'] ?? null);

        if (! $submission || $submission->office !== $message->office) {
            throw new DomainActionException('Receipt does not reference a known submission for this office.');
        }

        return DB::transaction(function () use ($message, $submission) {
            $log = $this->acknowledge->handle(
                $submission,
                $message->payload['office_ref'] ?? $message->external_id,
                $message->payload,
            );

            $message->update([
                'matter_id' => $submission->matter_id,
                'status' => OfficeMessageStatus::Processed,
                'actions' => $log,
                'processed_at' => now(),
                'error' => null,
            ]);

            return $message;
        });
    }

    private function updateOfficialFields(OfficeMessage $message, Matter $matter, array &$log): void
    {
        $payload = $message->payload ?? [];
        $updates = [];

        foreach ([
            'application_no', 'application_date', 'publication_no', 'publication_date',
            'registration_no', 'registration_date', 'expiry_date',
        ] as $field) {
            $value = $payload[$field] ?? null;
            if ($value && (string) $matter->{$field} !== (string) $value && empty($matter->{$field})) {
                $updates[$field] = $value;
            }
        }

        // The event's own headline fields win even without a payload
        $eventDate = $message->event_date?->toDateString();
        if ($message->event_type === OfficeEventType::Publication && ! $matter->publication_date && $eventDate) {
            $updates['publication_date'] = $eventDate;
        }
        if (in_array($message->event_type, [OfficeEventType::Grant, OfficeEventType::Registration], true)
            && ! $matter->registration_date && $eventDate) {
            $updates['registration_date'] = $eventDate;
        }
        if ($message->registration_no && ! $matter->registration_no) {
            $updates['registration_no'] = $message->registration_no;
        }

        if ($updates) {
            $matter->update($updates);
            $log[] = 'Updated matter fields: '.implode(', ', array_keys($updates));
        }
    }

    private function updateStatus(OfficeEventType $event, Matter $matter, array &$log): void
    {
        $status = $event->matterStatus();

        if ($status && $matter->status !== $status) {
            $matter->update(['status' => $status]);
            $log[] = "Set matter status to {$status->label()}";
        }
    }

    private function completeTasks(OfficeEventType $event, Matter $matter, array &$log): void
    {
        $tasks = $matter->tasks()
            ->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
            ->whereHas('workflowStep', fn ($q) => $q->where('completed_by_event', $event->value))
            ->get();

        foreach ($tasks as $task) {
            $task->update(['status' => TaskStatus::Completed, 'completed_at' => now()]);
            $log[] = "Completed task “{$task->title}”";
        }
    }

    private function applyWorkflows(OfficeEventType $event, Matter $matter, Carbon|\Carbon\CarbonInterface $eventDate, array &$log): void
    {
        $trigger = $event->trigger();

        if (! $trigger) {
            return;
        }

        $workflows = Workflow::where('is_active', true)
            ->where('trigger_event', $trigger)
            ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
            ->whereHas('steps')
            ->get();

        foreach ($workflows as $workflow) {
            // One-shot events don't re-apply; office actions recur.
            $alreadyApplied = $event !== OfficeEventType::OfficeAction
                && $matter->tasks()->whereHas('workflowStep', fn ($q) => $q->where('workflow_id', $workflow->id))->exists();

            if ($alreadyApplied) {
                continue;
            }

            $tasks = $this->workflows->apply($workflow, $matter, $eventDate);
            $log[] = "Applied workflow “{$workflow->name}” — {$tasks->count()} task(s) from {$eventDate->toDateString()}";
        }
    }

    private function addOfficialFees(OfficeMessage $message, Matter $matter, array &$log): void
    {
        foreach ($message->payload['fees'] ?? [] as $fee) {
            if (empty($fee['amount']) || empty($fee['currency'])) {
                continue;
            }

            $disbursement = $this->disbursements->handle($matter, [
                'date' => ($message->event_date ?? now())->toDateString(),
                'description' => $fee['description'] ?? "{$message->officeName()} official fee",
                'supplier' => $message->officeName(),
                'cost_amount' => $fee['amount'],
                'cost_currency' => $fee['currency'],
            ]);
            $log[] = sprintf(
                'Added official fee: %s %s → billed %s %s',
                $fee['currency'], number_format((float) $fee['amount'], 2),
                $disbursement->currency_code, number_format((float) $disbursement->amount, 2)
            );
        }
    }

    /** Documents riding on the message (base64 in the exchange payload) are filed on the docket. */
    private function fileDocuments(OfficeMessage $message, Matter $matter, array &$log): void
    {
        foreach ($message->payload['documents'] ?? [] as $doc) {
            if (empty($doc['name']) || empty($doc['content_base64'])) {
                continue;
            }

            $content = base64_decode($doc['content_base64'], true);
            if ($content === false) {
                continue;
            }

            $document = $this->documents->fromContent($matter, $doc['name'], $content, [
                'title' => $doc['title'] ?? pathinfo($doc['name'], PATHINFO_FILENAME),
                'category' => \App\Enums\DocumentCategory::tryFrom($doc['category'] ?? '')
                    ?? \App\Enums\DocumentCategory::OfficeAction,
                'source' => 'office',
                'mime' => $doc['mime'] ?? null,
                'linked_type' => OfficeMessage::class,
                'linked_id' => $message->id,
            ]);
            $log[] = "Filed document “{$document->title}” from the office message";
        }
    }

    private function draftCommunications(OfficeEventType $event, Matter $matter, array &$log): void
    {
        $templates = CommTemplate::where('is_active', true)
            ->where('auto_event', $event->value)
            ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
            ->get();

        foreach ($templates as $template) {
            $rendered = $this->renderer->render($template, $matter);
            $contact = $matter->mainContact();

            $matter->communications()->create([
                'comm_template_id' => $template->id,
                'channel' => $template->channel,
                'recipient_name' => $contact?->name,
                'recipient_email' => $contact?->email,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'status' => 'draft',
                'created_by' => $matter->responsible_user_id,
            ]);
            $log[] = "Drafted communication “{$template->name}” for review";
        }
    }
}
