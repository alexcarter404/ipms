<?php

namespace App\Actions\Entities;

use App\Models\ClientEntity;
use Illuminate\Support\Facades\DB;

class UpdateClientEntity
{
    public function handle(ClientEntity $entity, array $data): ClientEntity
    {
        return DB::transaction(function () use ($entity, $data) {
            // The default flag is only ever granted here; to remove it,
            // make another entity the default.
            $makeDefault = $data['is_default'] ?? false;
            unset($data['is_default']);

            $entity->update($data);

            if ($makeDefault) {
                $entity->makeDefault();
            }

            return $entity;
        });
    }
}
