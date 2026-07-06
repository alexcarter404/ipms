<?php

namespace App\Services\Integrations\Transformers;

use App\Models\OfficeSubmission;

/**
 * Every IP office speaks a different filing dialect — the EPO's Online
 * Filing wants a Form 1001-style request with a fee sheet, the USPTO's
 * Patent Center wants its own JSON, and so on. A transformer is the
 * per-office adapter that (a) validates the office's prerequisites
 * before anything is sent, and (b) converts our canonical submission
 * package into the office's wire format. The rest of the pipeline —
 * submissions, connectors, receipts — never changes.
 */
interface OfficePayloadTransformer
{
    /**
     * Office-specific prerequisites that must hold before submission.
     *
     * @return list<string> blocking issues (empty = ready to file)
     */
    public function validate(OfficeSubmission $submission): array;

    /**
     * The office's wire format. Canonical keys are preserved so the
     * exchange record stays readable; office blocks sit alongside.
     */
    public function transform(OfficeSubmission $submission): array;
}
