<?php

namespace App\Services;

use App\Enums\BillableStatus;
use App\Models\Charge;
use App\Models\Disbursement;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\Renewal;
use App\Models\TimeEntry;
use App\Support\MoneyMinor;

/**
 * The ad-hoc report engine: five practice-shaped datasets with shared
 * filters (client, attorney, status, date window), each producing a
 * header row + data rows ready for the screen or a CSV.
 */
class ReportRunner
{
    public const TYPES = [
        'matters' => 'Matters register',
        'tasks' => 'Tasks & deadlines',
        'renewals' => 'Renewals',
        'wip' => 'Unbilled WIP items',
        'invoices' => 'Invoices',
    ];

    /** @return array{headers: list<string>, rows: list<list<mixed>>} */
    public function run(string $type, array $filters = []): array
    {
        return match ($type) {
            'matters' => $this->matters($filters),
            'tasks' => $this->tasks($filters),
            'renewals' => $this->renewals($filters),
            'wip' => $this->wip($filters),
            'invoices' => $this->invoices($filters),
            default => ['headers' => [], 'rows' => []],
        };
    }

    private function matters(array $filters): array
    {
        $rows = Matter::query()
            ->with(['client:id,name', 'responsibleUser:id,name'])
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('responsible_user_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('application_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('application_date', '<=', $to))
            ->orderBy('reference')
            ->limit(500)
            ->get()
            ->map(fn (Matter $m) => [
                $m->reference, $m->title, $m->matter_type->value, $m->country_code,
                $m->status->value, $m->client?->name, $m->responsibleUser?->name,
                $m->application_no, $m->application_date?->toDateString(),
                $m->registration_no, $m->registration_date?->toDateString(),
            ]);

        return [
            'headers' => ['Reference', 'Title', 'Type', 'Ctry', 'Status', 'Client', 'Attorney',
                'Application no', 'Filed', 'Registration no', 'Granted'],
            'rows' => $rows->all(),
        ];
    }

    private function tasks(array $filters): array
    {
        $rows = MatterTask::query()
            ->with(['matter:id,reference,client_id', 'matter.client:id,name', 'assignee:id,name'])
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->whereHas('matter', fn ($m) => $m->where('client_id', $id)))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')
            ->limit(500)
            ->get()
            ->map(fn (MatterTask $t) => [
                $t->due_date?->toDateString(), $t->title, $t->status->value,
                $t->priority->value, $t->is_critical ? 'yes' : 'no',
                $t->matter?->reference, $t->matter?->client?->name, $t->assignee?->name,
            ]);

        return [
            'headers' => ['Due', 'Task', 'Status', 'Priority', 'Critical', 'Matter', 'Client', 'Assignee'],
            'rows' => $rows->all(),
        ];
    }

    private function renewals(array $filters): array
    {
        $rows = Renewal::query()
            ->with(['matter:id,reference,client_id,country_code', 'matter.client:id,name'])
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->whereHas('matter', fn ($m) => $m->where('client_id', $id)))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereHas('matter', fn ($m) => $m->where('responsible_user_id', $id)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')
            ->limit(500)
            ->get()
            ->map(fn (Renewal $r) => [
                $r->due_date->toDateString(), $r->matter?->reference, $r->matter?->client?->name,
                $r->matter?->country_code, $r->cycle, $r->status->value,
                $r->official_fee, $r->currency,
            ]);

        return [
            'headers' => ['Due', 'Matter', 'Client', 'Ctry', 'Year', 'Status', 'Official fee', 'Currency'],
            'rows' => $rows->all(),
        ];
    }

    private function wip(array $filters): array
    {
        $sets = [
            ['time', TimeEntry::query()->with('user:id,name'), 'work_date'],
            ['disbursement', Disbursement::query(), 'date'],
            ['charge', Charge::query(), 'date'],
        ];
        $rows = collect();

        foreach ($sets as [$kind, $query, $dateColumn]) {
            $items = $query
                ->with(['matter:id,reference,client_id', 'matter.client:id,name'])
                ->where('status', BillableStatus::Billable)
                ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->whereHas('matter', fn ($m) => $m->where('client_id', $id)))
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereHas('matter', fn ($m) => $m->where('responsible_user_id', $id)))
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate($dateColumn, '>=', $from))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate($dateColumn, '<=', $to))
                ->limit(500)
                ->get()
                ->map(fn ($item) => [
                    $item->{$dateColumn}->toDateString(),
                    $kind,
                    $item->matter?->reference,
                    $item->matter?->client?->name,
                    $item->narrative ?? $item->description,
                    $item->amount,
                    $item->currency_code,
                    $item->base_amount,
                ]);
            $rows = $rows->concat($items);
        }

        return [
            'headers' => ['Date', 'Kind', 'Matter', 'Client', 'Description', 'Amount', 'Currency', 'Base amount'],
            'rows' => $rows->sortBy(0)->values()->take(500)->all(),
        ];
    }

    private function invoices(array $filters): array
    {
        $rows = Invoice::query()
            ->with(['client:id,name', 'entity:id,name'])
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('issued_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('issued_at', '<=', $to))
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (Invoice $i) => [
                $i->displayNumber(), $i->issued_at?->toDateString(), $i->client?->name,
                $i->entity?->name, $i->status->value, $i->currency_code,
                $i->subtotal, $i->tax_amount, $i->total, $i->balance(),
            ]);

        return [
            'headers' => ['Number', 'Issued', 'Client', 'Entity', 'Status', 'Currency',
                'Subtotal', 'Tax', 'Total', 'Balance'],
            'rows' => $rows->all(),
        ];
    }

    /** The report as CSV text. */
    public function toCsv(array $result): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $result['headers']);
        foreach ($result['rows'] as $row) {
            fputcsv($handle, array_map(fn ($v) => $v ?? '', $row));
        }
        rewind($handle);

        return stream_get_contents($handle);
    }
}
