<?php

namespace App\Actions\Entities;

use App\Exceptions\DomainActionException;
use App\Models\ClientEntity;

class DeleteClientEntity
{
    public function handle(ClientEntity $entity): void
    {
        if ($entity->matters()->exists()) {
            throw new DomainActionException('This entity is the billing entity on matters — reassign them first.');
        }

        if ($entity->is_default) {
            throw new DomainActionException('The default entity cannot be deleted — make another entity the default first.');
        }

        $entity->delete();
    }
}
