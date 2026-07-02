<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\Contact;

class SaveContact
{
    public function create(Client $client, array $data): Contact
    {
        return $client->contacts()->create($data);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact;
    }
}
