<?php

namespace App\Repositories;

use App\Models\Renewal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RenewalRepository
{
    public function paginateFiltered(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Renewal::query()
            ->with('matter:id,reference,title,matter_type,country_code,client_id', 'matter.client:id,name')
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $status === 'open' ? $q->open() : $q->where('status', $status),
                fn ($q) => $q->open()
            )
            ->when($filters['due_within'] ?? null, fn ($q, $days) => $q->dueWithin((int) $days))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->whereHas(
                'matter',
                fn ($m) => $m->where('reference', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")
            ))
            ->orderBy('due_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function openDueWithinCount(int $days): int
    {
        return Renewal::open()->dueWithin($days)->count();
    }

    public function upcoming(int $limit): Collection
    {
        return Renewal::open()
            ->with('matter:id,reference,title,matter_type,country_code')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }
}
