<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClientRepository
{
    public function paginateSearch(?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Client::query()
            ->when(auth()->user(), fn ($q, $user) => $q->visibleTo($user))
            ->withCount('matters')
            ->when($search, fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Client detail page: contacts (primary first) + entities with usage. */
    public function loadForDisplay(Client $client): Client
    {
        return $client->load([
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'entities' => fn ($q) => $q->withCount('matters')->with('billingAgreement'),
        ]);
    }

    public function paginateMatters(Client $client, int $perPage = 10): LengthAwarePaginator
    {
        return $client->matters()
            ->with('responsibleUser:id,name')
            ->latest()
            ->paginate($perPage);
    }

    public function options(): Collection
    {
        return Client::orderBy('name')->get(['id', 'name']);
    }

    /** Options with entities for the matter form's billing-entity picker. */
    public function optionsWithEntities(): Collection
    {
        return Client::with('entities:id,client_id,name,is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function count(): int
    {
        return Client::count();
    }

    public function searchTypeahead(string $like, int $limit): Collection
    {
        return Client::query()
            ->when(auth()->user(), fn ($q, $user) => $q->visibleTo($user))
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->limit($limit)
            ->get();
    }
}
