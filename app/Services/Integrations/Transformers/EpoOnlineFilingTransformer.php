<?php

namespace App\Services\Integrations\Transformers;

use App\Enums\SubmissionType;
use App\Models\OfficeSubmission;

/**
 * The EPO Online Filing dialect. An EP filing isn't a flat payload:
 * the request is built up from parts — a Form 1001-style request for
 * grant (applicant with a full address, representative, title),
 * document references, and a fee sheet computed per the fee schedule.
 * This transformer assembles those parts from the matter/entity data
 * and refuses to submit until the mandatory ones exist.
 */
class EpoOnlineFilingTransformer implements OfficePayloadTransformer
{
    public function validate(OfficeSubmission $submission): array
    {
        $matter = $submission->matter;
        $entity = $matter->effectiveBillingEntity();
        $issues = [];

        switch ($submission->submission_type) {
            case SubmissionType::Filing:
                if (! $matter->title) {
                    $issues[] = 'EP request for grant requires a title of invention';
                }
                if (! $matter->client?->name) {
                    $issues[] = 'EP request for grant requires an applicant';
                }
                if (! $entity?->address) {
                    $issues[] = 'EPO Form 1001 requires the applicant\'s full address — set it on the billing entity';
                }
                if (! $matter->responsibleUser?->name) {
                    $issues[] = 'EP filings require a representative — set the responsible attorney';
                }
                break;

            case SubmissionType::OaResponse:
                if (! $matter->application_no) {
                    $issues[] = 'An EP office action response must reference the application number';
                }
                if (! $submission->notes) {
                    $issues[] = 'Attach the response text (notes) — the EPO rejects empty responses';
                }
                break;

            case SubmissionType::RenewalPayment:
                if (empty($submission->payload['renewal'])) {
                    $issues[] = 'No open renewal cycle to pay — generate the renewal schedule first';
                }
                break;

            case SubmissionType::Document:
                if (! $submission->notes) {
                    $issues[] = 'Describe the document being filed';
                }
                break;
        }

        return $issues;
    }

    public function transform(OfficeSubmission $submission): array
    {
        $matter = $submission->matter;
        $entity = $matter->effectiveBillingEntity();

        // Canonical keys stay for the exchange record; the EPO blocks
        // carry the office's own structure.
        return $submission->payload + [
            'ep_request' => [
                'form' => $submission->submission_type === SubmissionType::Filing ? 'EP1001' : 'EP1038',
                'application_number' => $matter->application_no,
                'applicants' => [[
                    'name' => $matter->client?->name,
                    'address' => $entity?->address,
                    'country' => $entity?->country_code ?? $matter->country_code,
                ]],
                'representative' => [
                    'name' => $matter->responsibleUser?->name,
                ],
                'title_of_invention' => $matter->title,
                'priority_claims' => array_values(array_filter([
                    $matter->priority_no ? [
                        'number' => $matter->priority_no,
                        'date' => $matter->priority_date?->toDateString(),
                    ] : null,
                ])),
            ],
            'fee_sheet' => $this->feeSheet($submission),
        ];
    }

    /** Indicative EP fee codes for the package's fee sheet. */
    private function feeSheet(OfficeSubmission $submission): array
    {
        return match ($submission->submission_type) {
            SubmissionType::Filing => [
                ['code' => '001', 'description' => 'Filing fee — EP direct online', 'amount' => 135, 'currency' => 'EUR'],
                ['code' => '002', 'description' => 'European search fee', 'amount' => 1520, 'currency' => 'EUR'],
            ],
            SubmissionType::RenewalPayment => array_values(array_filter([
                ($renewal = $submission->payload['renewal'] ?? null) ? [
                    'code' => '33'.($renewal['cycle'] ?? ''),
                    'description' => 'Renewal fee — year '.($renewal['cycle'] ?? '?'),
                    'amount' => $renewal['official_fee'] ?? null,
                    'currency' => 'EUR',
                ] : null,
            ])),
            default => [],
        };
    }
}
