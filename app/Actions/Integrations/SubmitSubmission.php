<?php

namespace App\Actions\Integrations;

use App\Enums\SubmissionStatus;
use App\Exceptions\DomainActionException;
use App\Models\OfficeSubmission;
use App\Services\Integrations\IngestOfficeMessages;
use App\Services\Integrations\Transformers\GenericTransformer;
use App\Services\Integrations\Transformers\OfficePayloadTransformer;

class SubmitSubmission
{
    public function __construct(
        private IngestOfficeMessages $connectors,
        private AcknowledgeSubmission $acknowledge,
    ) {
    }

    public function handle(OfficeSubmission $submission): OfficeSubmission
    {
        if (! in_array($submission->status, [SubmissionStatus::Draft, SubmissionStatus::Failed], true)) {
            throw new DomainActionException('Only draft or failed submissions can be submitted.');
        }

        // The office's dialect gates the gate: nothing leaves until the
        // office-specific prerequisites hold, and what leaves is the
        // office's wire format, not our canonical package.
        $transformer = $this->transformerFor($submission->office);

        if ($issues = $transformer->validate($submission)) {
            throw new DomainActionException(
                'The office rejected the package: '.implode('; ', $issues).'.'
            );
        }

        try {
            $result = $this->connectors->connector($submission->office)->submit(
                $transformer->transform($submission) + ['submission_id' => $submission->id]
            );
        } catch (\Throwable $e) {
            $submission->update([
                'status' => SubmissionStatus::Failed,
                'error' => $e->getMessage(),
            ]);

            throw new DomainActionException("Submission failed: {$e->getMessage()}");
        }

        $submission->update([
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
            'error' => null,
        ]);

        if ($result['acknowledged'] ?? false) {
            $this->acknowledge->handle($submission, $result['external_ref'] ?? null, $result['receipt'] ?? null);
        }

        return $submission->fresh();
    }

    private function transformerFor(string $office): OfficePayloadTransformer
    {
        $class = config("integrations.offices.{$office}.transformer");

        return $class ? app($class) : new GenericTransformer;
    }
}
