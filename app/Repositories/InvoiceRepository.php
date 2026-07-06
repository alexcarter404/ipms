<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository
{
    public function paginateFiltered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['client:id,name', 'entity:id,name', 'matter:id,reference'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadForDisplay(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'client:id,name',
            'entity',
            'matter:id,reference,title',
            'lines.matter:id,reference,title',
            'payments',
        ]);
    }

    /** Next sequential number for the issue year, e.g. INV-2026-0042. */
    public function nextNumber(): string
    {
        $year = now()->year;
        $count = Invoice::where('invoice_no', 'like', "INV-{$year}-%")->count();

        return sprintf('INV-%d-%04d', $year, $count + 1);
    }
}
