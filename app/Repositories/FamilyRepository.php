<?php

namespace App\Repositories;

use App\Models\Family;
use Illuminate\Support\Collection;

class FamilyRepository
{
    public function options(): Collection
    {
        return Family::orderBy('reference')->get(['id', 'reference', 'name']);
    }
}
