<?php

namespace App\Repositories;

use App\Models\MatterTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskRepository
{
    public function paginateFiltered(array $filters, ?int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return MatterTask::query()
            ->with(['matter:id,reference,title', 'assignee:id,name'])
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $status === 'open' ? $q->open() : $q->where('status', $status),
                fn ($q) => $q->open()
            )
            ->when(($filters['assignee'] ?? null) === 'me' && $userId, fn ($q) => $q->where('assigned_to', $userId))
            ->when($filters['overdue'] ?? false, fn ($q) => $q->whereDate('due_date', '<', now()))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$term}%")
                    ->orWhereHas('matter', fn ($m) => $m->where('reference', 'like', "%{$term}%"))
            ))
            ->orderBy('due_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function openCount(): int
    {
        return MatterTask::open()->count();
    }

    public function overdueCount(): int
    {
        return MatterTask::overdue()->count();
    }

    public function upcoming(int $limit): Collection
    {
        return MatterTask::open()
            ->with(['matter:id,reference,title', 'assignee:id,name'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    public function searchTypeahead(string $like, int $limit): Collection
    {
        return MatterTask::query()
            ->with('matter:id,reference')
            ->whereNotNull('matter_id')
            ->where('title', 'like', $like)
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }
}
