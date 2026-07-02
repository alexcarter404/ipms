<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class CreateClient
{
    /** Every client starts with a default legal entity mirroring it. */
    public function handle(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create($data);

            $client->entities()->create([
                'name' => $client->name,
                'country_code' => $client->country_code,
                'billing_email' => $client->email,
                'is_default' => true,
            ]);

            return $client;
        });
    }
}
