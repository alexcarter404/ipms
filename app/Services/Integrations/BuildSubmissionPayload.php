<?php

namespace App\Services\Integrations;

use App\Enums\RenewalStatus;
use App\Enums\SubmissionType;
use App\Models\Matter;
use App\Models\MatterTask;

/**
 * Assembles the outbound package for an office from the matter's own
 * data — the same fields whichever channel carries it.
 */
class BuildSubmissionPayload
{
    public function handle(Matter $matter, SubmissionType $type, ?MatterTask $task = null, ?string $notes = null): array
    {
        $base = [
            'submission_type' => $type->value,
            'matter_reference' => $matter->reference,
            'matter_type' => $matter->matter_type->value,
            'country' => $matter->country_code,
            'title' => $matter->title,
            'applicant' => $matter->client?->name,
            'application_no' => $matter->application_no,
            'registration_no' => $matter->registration_no,
            'notes' => $notes,
        ];

        return $base + match ($type) {
            SubmissionType::Filing => [
                'filing_route' => $matter->filing_route,
                'priority_no' => $matter->priority_no,
                'priority_date' => $matter->priority_date?->toDateString(),
                'classes' => $matter->classes->map(fn ($class) => [
                    'class' => $class->class_number,
                    'specification' => $class->specification,
                ])->all(),
            ],
            SubmissionType::OaResponse => [
                'responds_to' => $task?->title,
                'response_due' => $task?->due_date?->toDateString(),
            ],
            SubmissionType::RenewalPayment => [
                'renewal' => ($renewal = $matter->renewals()
                    ->whereIn('status', [RenewalStatus::Upcoming, RenewalStatus::ReminderSent, RenewalStatus::Instructed])
                    ->orderBy('due_date')->first()) ? [
                        'cycle' => $renewal->cycle,
                        'due_date' => $renewal->due_date->toDateString(),
                        'official_fee' => $renewal->official_fee,
                    ] : null,
            ],
            SubmissionType::Document => [
                'document_description' => $notes,
            ],
        };
    }
}
