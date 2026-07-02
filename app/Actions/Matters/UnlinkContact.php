<?php

namespace App\Actions\Matters;

use App\Models\Contact;
use App\Models\Matter;

class UnlinkContact
{
    public function handle(Matter $matter, Contact $contact, string $role): void
    {
        $matter->contacts()->wherePivot('role', $role)->detach($contact->id);
    }
}
