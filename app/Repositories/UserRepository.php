<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

class UserRepository
{
    public function options(): Collection
    {
        return User::orderBy('name')->get(['id', 'name']);
    }
}
