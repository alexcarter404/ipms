<?php

namespace App\Services\Integrations;

use App\Models\Matter;
use App\Models\RegisterCheck;

/**
 * Scheduled register hygiene: compare each matter's official fields
 * against the office record and flag drift — the classic "our docket
 * says X, the register says Y" catch that competitors run weekly.
 */
class RegisterReconciliation
{
    /** Official fields worth comparing (dates as Y-m-d). */
    private const FIELDS = [
        'application_date', 'publication_no', 'publication_date',
        'registration_no', 'registration_date', 'expiry_date',
    ];

    public function __construct(private IngestOfficeMessages $connectors)
    {
    }

    /** @return array{checked: int, drift: int} */
    public function run(): array
    {
        $map = config('integrations.office_by_country', []);
        $stats = ['checked' => 0, 'drift' => 0];

        $matters = Matter::query()
            ->whereNotNull('application_no')
            ->whereIn('country_code', array_keys($map))
            ->get();

        foreach ($matters as $matter) {
            $check = $this->check($matter, $map[$matter->country_code]);
            if ($check) {
                $stats['checked']++;
                $stats['drift'] += $check->status === 'drift' ? 1 : 0;
            }
        }

        return $stats;
    }

    public function check(Matter $matter, string $office): ?RegisterCheck
    {
        $record = $this->connectors->connector($office)->lookup($matter->application_no);

        // Supersede this matter's previous unresolved checks
        RegisterCheck::where('matter_id', $matter->id)->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);

        if ($record === null) {
            // Informational: freshly filed cases aren't on the register
            // yet, so an absent record never blocks the open queue.
            return RegisterCheck::create([
                'matter_id' => $matter->id, 'office' => $office,
                'status' => 'not_found', 'checked_at' => now(),
                'resolved_at' => now(),
            ]);
        }

        $differences = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $record)) {
                continue; // the office record doesn't speak to this field
            }

            $ours = $matter->{$field};
            $ours = $ours instanceof \DateTimeInterface ? $ours->format('Y-m-d') : ($ours ?: null);
            $theirs = $record[$field] ?: null;

            if ((string) $ours !== (string) $theirs) {
                $differences[] = ['field' => $field, 'ours' => $ours, 'theirs' => $theirs];
            }
        }

        return RegisterCheck::create([
            'matter_id' => $matter->id,
            'office' => $office,
            'status' => $differences ? 'drift' : 'ok',
            'differences' => $differences ?: null,
            'checked_at' => now(),
            'resolved_at' => $differences ? null : now(),
        ]);
    }

    /** Take the office's word: apply the recorded differences to the matter. */
    public function acceptOfficeValues(RegisterCheck $check): Matter
    {
        $matter = $check->matter;

        $updates = collect($check->differences ?? [])
            ->mapWithKeys(fn ($difference) => [$difference['field'] => $difference['theirs']])
            ->all();

        if ($updates) {
            $matter->update($updates);
        }

        $check->update(['status' => 'ok', 'resolved_at' => now()]);

        return $matter->fresh();
    }
}
