<?php

namespace App\Repositories;

use App\Models\Party;
use Illuminate\Support\Collection;

class PartyRepository
{
    public function options(): Collection
    {
        return Party::orderBy('name')->get(['id', 'name']);
    }

    /** Parties appearing on at least one matter, with a matter to link to. */
    public function searchTypeahead(string $like, int $limit): Collection
    {
        return Party::query()
            ->with(['matters' => fn ($q) => $q->select('matters.id')->limit(1)])
            ->withCount('matters')
            ->where('name', 'like', $like)
            ->whereHas('matters')
            ->limit($limit)
            ->get();
    }
}
