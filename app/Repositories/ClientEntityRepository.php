<?php

namespace App\Repositories;

use App\Models\ClientEntity;
use Illuminate\Support\Collection;

class ClientEntityRepository
{
    public function searchTypeahead(string $like, int $limit): Collection
    {
        return ClientEntity::query()
            ->with('client:id,name')
            ->where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('vat_number', 'like', $like)
                ->orWhere('registration_no', 'like', $like))
            ->limit($limit)
            ->get();
    }
}
