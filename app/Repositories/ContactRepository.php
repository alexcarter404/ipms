<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Support\Collection;

class ContactRepository
{
    public function forClient(Client $client): Collection
    {
        return $client->contacts()->orderBy('name')->get();
    }

    public function searchTypeahead(string $like, int $limit): Collection
    {
        return Contact::query()
            ->with('client:id,name')
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit($limit)
            ->get();
    }
}
