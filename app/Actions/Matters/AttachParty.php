<?php

namespace App\Actions\Matters;

use App\Exceptions\DomainActionException;
use App\Models\Matter;
use App\Models\Party;

class AttachParty
{
    /** Attach an existing party, or create and attach a new one. */
    public function handle(Matter $matter, array $data): void
    {
        $partyId = $data['party_id']
            ?? Party::create([
                'name' => $data['name'],
                'type' => $data['party_type'] ?? 'individual',
            ])->id;

        $exists = $matter->parties()
            ->wherePivot('party_id', $partyId)
            ->wherePivot('role', $data['role'])
            ->exists();

        if ($exists) {
            throw new DomainActionException('That party already has this role on the matter.');
        }

        $matter->parties()->attach($partyId, [
            'role' => $data['role'],
            'sort_order' => $matter->parties()->wherePivot('role', $data['role'])->count(),
        ]);
    }
}
