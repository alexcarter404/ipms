<?php

namespace App\Actions\Communications;

use App\Models\Communication;
use App\Models\Matter;
use App\Models\User;

class ComposeCommunication
{
    public function handle(Matter $matter, array $data, User $author): Communication
    {
        return $matter->communications()->create($data + [
            'status' => 'draft',
            'created_by' => $author->id,
        ]);
    }
}
