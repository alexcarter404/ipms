<?php

namespace App\Actions\Matters;

use App\Enums\ContactType;
use App\Exceptions\DomainActionException;
use App\Models\Matter;

class LinkContact
{
    /**
     * Link an existing contact of the matter's client, or create a new
     * contact on the client and link it — in a given role.
     */
    public function handle(Matter $matter, array $data): void
    {
        $contactId = $data['contact_id']
            ?? $matter->client->contacts()->create([
                'name' => $data['name'],
                'type' => $data['contact_type'] ?? ContactType::Person,
                'email' => $data['email'] ?? null,
            ])->id;

        $exists = $matter->contacts()
            ->wherePivot('contact_id', $contactId)
            ->wherePivot('role', $data['role'])
            ->exists();

        if ($exists) {
            throw new DomainActionException('That contact already has this role on the matter.');
        }

        $matter->contacts()->attach($contactId, ['role' => $data['role']]);
    }
}
