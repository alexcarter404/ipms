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
     * Pull pending inbound messages. Each message is an array with:
     * external_id, event_type, and optionally application_no,
     * registration_no, event_date, summary, and a payload of
     * office-specific fields (publication_no, fee amounts, …).
     *
     * @return list<array>
     */
    public function fetch(): array;
}
