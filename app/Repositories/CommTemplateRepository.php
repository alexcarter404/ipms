<?php

namespace App\Repositories;

use App\Models\CommTemplate;
use App\Models\Matter;
use Illuminate\Support\Collection;

class CommTemplateRepository
{
    public function allWithUsage(): Collection
    {
        return CommTemplate::withCount('communications')->orderBy('name')->get();
    }

    /** Active templates applicable to a matter's type (or any type). */
    public function activeForMatter(Matter $matter): Collection
    {
        return CommTemplate::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
            ->get(['id', 'name', 'channel']);
    }

    public function searchTypeahead(string $like, int $limit): Collection
    {
        return CommTemplate::where('name', 'like', $like)->limit($limit)->get();
    }
}
