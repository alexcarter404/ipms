<?php

namespace App\Actions\Entities;

use App\Models\Client;
use App\Models\ClientEntity;
use Illuminate\Support\Facades\DB;

class CreateClientEntity
{
    public function handle(Client $client, array $data): ClientEntity
    {
        return DB::transaction(function () use ($client, $data) {
            $entity = $client->entities()->create($data);

            // First entity is always the default; an explicit request wins.
            if (($data['is_default'] ?? false) || $client->entities()->count() === 1) {
                $entity->makeDefault();
            }

            return $entity;
        });
    }
}
