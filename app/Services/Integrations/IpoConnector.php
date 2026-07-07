<?php

namespace App\Services\Integrations;

/**
 * The office-exchange seam. A connector knows how to pull inbound
 * messages from one IP office channel. The file-drop driver covers
 * SFTP-style exchanges today; REST drivers (EPO OPS, USPTO ODP, WIPO
 * ePCT) implement the same contract with credentials later — the
 * ingestion pipeline never changes.
 */
interface IpoConnector
{
    /** The office code this connector serves (epo, uspto, …). */
    public function office(): string;

    /**
     * Push an outbound submission to the office. Returns
     * ['acknowledged' => bool, 'external_ref' => ?string,
     *  'receipt' => ?array] — asynchronous channels (file drop) return
     * unacknowledged and the receipt arrives later as an inbound
     * 'receipt' message referencing the submission id.
     *
     * @param  array  $payload  includes 'submission_id' for correlation
     */
    public function submit(array $payload): array;

    /**
     * Look up an application on the office register. Returns the
     * office's record (title, applicant, official numbers and dates,
     * status) or null when the number is unknown.
     */
    public function lookup(string $applicationNo): ?array;

    /**
     * Pull pending inbound messages. Each message is an array with:
     * external_id, event_type, and optionally application_no,
     * registration_no, event_date, summary, and a payload of
     * office-specific fields (publication_no, fee amounts, …).
     *
     * @return list<array>
     */
    public function fetch(): array;
}
