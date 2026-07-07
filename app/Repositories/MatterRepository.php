<?php

namespace App\Repositories;

use App\Models\Matter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MatterRepository
{
    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Matter::query()
            ->when(auth()->user(), fn ($q, $user) => $q->visibleTo($user))
            ->with(['client:id,name', 'responsibleUser:id,name'])
            ->search($filters['search'] ?? null)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('matter_type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['country'] ?? null, fn ($q, $country) => $q->where('country_code', $country))
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->when(
                ($filters['sort'] ?? null) === 'reference',
                fn ($q) => $q->orderBy('reference'),
                fn ($q) => $q->latest()
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Eager-load everything the matter detail page shows. */
    public function loadForDisplay(Matter $matter): Matter
    {
        return $matter->load([
            'client:id,name,code',
            // Full entity: billingCurrency()/tax fall back to its columns
            'billingEntity.billingAgreement',
            'contacts',
            'family:id,reference,name',
            'parent:id,reference,title',
            'children:id,parent_id,reference,title,country_code,status',
            'responsibleUser:id,name',
            'parties',
            'classes',
            'renewals',
            'tasks.assignee:id,name',
            'communications.creator:id,name',
            'communications.template:id,name',
        ]);
    }

    /** @return Collection<int, Matter> id/reference/title options for parent pickers */
    public function referenceOptions(): Collection
    {
        return Matter::orderBy('reference')->get(['id', 'reference', 'title']);
    }

    public function activeCount(): int
    {
        return Matter::active()->count();
    }

    /** @return Collection<string, int> active matter counts keyed by type */
    public function activeCountsByType(): Collection
    {
        return Matter::active()
            ->selectRaw('matter_type, count(*) as total')
            ->groupBy('matter_type')
            ->pluck('total', 'matter_type');
    }

    public function recent(int $limit): Collection
    {
        return Matter::with('client:id,name')->latest()->limit($limit)->get();
    }

    /** Typeahead hits; $like is an escaped LIKE pattern. */
    public function searchTypeahead(string $like, int $limit): Collection
    {
        return Matter::query()
            ->when(auth()->user(), fn ($q, $user) => $q->visibleTo($user))
            ->with('client:id,name')
            ->where(fn ($w) => $w
                ->where('reference', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('application_no', 'like', $like)
                ->orWhere('registration_no', 'like', $like))
            ->limit($limit)
            ->get();
    }
}
