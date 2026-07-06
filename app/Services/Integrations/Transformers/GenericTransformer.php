<?php

namespace App\Services\Integrations\Transformers;

use App\Models\OfficeSubmission;

/**
 * Offices without a dedicated dialect yet: the canonical package goes
 * out as-is.
 */
class GenericTransformer implements OfficePayloadTransformer
{
    public function validate(OfficeSubmission $submission): array
    {
        return [];
    }

    public function transform(OfficeSubmission $submission): array
    {
        return $submission->payload;
    }
}
