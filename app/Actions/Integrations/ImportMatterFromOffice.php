<?php

namespace App\Actions\Integrations;

use App\Exceptions\DomainActionException;
use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use App\Services\Integrations\IngestOfficeMessages;

/**
 * Create a matter straight from the office register: give it an
 * application number and the official record fills the docket —
 * title, type, dates, numbers, status. The reference is generated
 * from the type's sequence.
 */
class ImportMatterFromOffice
{
    private const TYPE_PREFIX = [
        'patent' => 'P', 'trademark' => 'TM', 'design' => 'D',
        'copyright' => 'C', 'domain' => 'DN',
    ];

    public function __construct(private IngestOfficeMessages $connectors) {}

    public function handle(string $office, string $applicationNo, Client $client, User $user): Matter
    {
        $record = $this->connectors->connector($office)->lookup($applicationNo);

        if (! $record) {
            throw new DomainActionException(
                "The {$office} register has no record of {$applicationNo} — check the number."
            );
        }

        $type = $record['matter_type'] ?? 'patent';

        return Matter::create([
            'reference' => $this->nextReference($type),
            'matter_type' => $type,
            'title' => $record['title'] ?? "Imported {$applicationNo}",
            'client_id' => $client->id,
            'responsible_user_id' => $user->id,
            'country_code' => $record['country_code'] ?? strtoupper(substr($office, 0, 2)),
            'filing_route' => $record['filing_route'] ?? 'national',
            'status' => $record['status'] ?? 'filed',
            'application_no' => $record['application_no'] ?? $applicationNo,
            'application_date' => $record['application_date'] ?? null,
            'publication_no' => $record['publication_no'] ?? null,
            'publication_date' => $record['publication_date'] ?? null,
            'registration_no' => $record['registration_no'] ?? null,
            'registration_date' => $record['registration_date'] ?? null,
            'expiry_date' => $record['expiry_date'] ?? null,
            'description' => $record['abstract'] ?? null,
        ]);
    }

    private function nextReference(string $type): string
    {
        $prefix = self::TYPE_PREFIX[$type] ?? 'M';
        $year = now()->year;

        $last = Matter::where('reference', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }
}
