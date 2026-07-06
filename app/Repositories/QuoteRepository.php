<?php

namespace App\Repositories;

use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuoteRepository
{
    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Quote::query()
            ->with(['client:id,name', 'entity:id,name', 'matter:id,reference'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Next sequential number for the year, e.g. Q-2026-0007. */
    public function nextNumber(): string
    {
        $year = now()->year;
        $count = Quote::where('quote_no', 'like', "Q-{$year}-%")->count();

        return sprintf('Q-%d-%04d', $year, $count + 1);
    }
}
