<?php

namespace App\Actions\Clients;

use App\Exceptions\DomainActionException;
use App\Models\Client;

class DeleteClient
{
    public function handle(Client $client): void
    {
        if ($client->matters()->exists()) {
            throw new DomainActionException('Cannot delete a client with matters on record.');
        }

        $client->delete();
    }
}
