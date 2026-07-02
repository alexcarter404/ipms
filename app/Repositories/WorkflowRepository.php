<?php

namespace App\Repositories;

use App\Models\Matter;
use App\Models\Workflow;
use Illuminate\Support\Collection;

class WorkflowRepository
{
    public function allWithStepCounts(): Collection
    {
        return Workflow::withCount('steps')->orderBy('name')->get();
    }

    /** Active workflows applicable to a matter's type (or any type). */
    public function activeForMatter(Matter $matter): Collection
    {
        return Workflow::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
            ->with('steps:id,workflow_id,title,offset_value,offset_unit')
            ->get();
    }

    public function searchTypeahead(string $like, int $limit): Collection
    {
        return Workflow::where('name', 'like', $like)->limit($limit)->get();
    }
}
