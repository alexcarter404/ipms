<?php

namespace App\Services;

use App\Enums\AgreementType;
use App\Enums\BillableStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\DomainActionException;
use App\Models\ClientEntity;
use App\Models\Invoice;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns unbilled WIP (time, disbursements, charges) into a draft invoice
 * for a billing entity — from a single matter, or consolidated across
 * every matter billed to the entity. Fee-agreement aware (fixed and
 * stage matters bill charges rather than time; capped matters get a
 * per-matter cap adjustment), currency-converting, tax-snapshotting.
 */
class InvoiceBuilder
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    /**
     * @param array{include_time?: bool, include_disbursements?: bool, include_charges?: bool, through?: string|null} $options
     */
    public function draft(Matter $matter, array $options = []): Invoice
    {
        $entity = $matter->effectiveBillingEntity();

        if (! $entity) {
            throw new DomainActionException('This matter has no billing entity to invoice.');
        }

        return $this->create($entity, collect([$matter]), $options, $matter);
    }

    /**
     * Consolidated bill: one invoice covering the entity's matters.
     *
     * @param array{matter_ids?: array<int>|null, include_time?: bool, include_disbursements?: bool, include_charges?: bool, through?: string|null} $options
     */
    public function draftForEntity(ClientEntity $entity, array $options = []): Invoice
    {
        $matters = $entity->billedMattersQuery()
            ->when($options['matter_ids'] ?? null, fn ($q, $ids) => $q->whereKey($ids))
            ->orderBy('reference')
            ->get();

        return $this->create($entity, $matters, $options);
    }

    private function create(ClientEntity $entity, Collection $matters, array $options, ?Matter $single = null): Invoice
    {
        $currency = $single?->billingCurrency()
            ?? $entity->currency_code
            ?? config('billing.base_currency');

        $work = $matters
            ->map(fn (Matter $matter) => [
                'matter' => $matter,
                'items' => $this->unbilledItems($matter, $options),
            ])
            ->filter(fn (array $set) => $set['items']['time']->isNotEmpty()
                || $set['items']['disbursements']->isNotEmpty()
                || $set['items']['charges']->isNotEmpty())
            ->values();

        if ($work->isEmpty()) {
            throw new DomainActionException($single
                ? 'No unbilled work to invoice on this matter.'
                : 'No unbilled work to invoice for this entity.');
        }

        return DB::transaction(function () use ($entity, $work, $currency, $single) {
            $invoice = Invoice::create([
                'client_id' => $entity->client_id,
                'client_entity_id' => $entity->id,
                'matter_id' => $single?->id,
                'currency_code' => $currency,
                'status' => InvoiceStatus::Draft,
                'tax_name' => $entity->taxRate?->name,
                'tax_pct' => $entity->taxRate?->rate ?? 0,
            ]);

            $sort = 0;
            foreach ($work as $set) {
                $this->addMatterLines($invoice, $set['matter'], $set['items'], $currency, $sort);
            }

            $subtotal = round((float) $invoice->lines()->sum('line_total'), 2);
            $taxAmount = round($subtotal * (float) $invoice->tax_pct / 100, 2);
            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => round($subtotal + $taxAmount, 2),
            ]);

            return $invoice->load('lines');
        });
    }

    /** @return array{time: Collection, disbursements: Collection, charges: Collection} */
    private function unbilledItems(Matter $matter, array $options): array
    {
        $through = $options['through'] ?? null;
        $billsTime = ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->billsTime();

        return [
            'time' => ($options['include_time'] ?? true) && $billsTime
                ? $matter->timeEntries()->billable()
                    ->when($through, fn ($q) => $q->where('work_date', '<=', $through))
                    ->with('user')->orderBy('work_date')->get()
                : collect(),
            'disbursements' => ($options['include_disbursements'] ?? true)
                ? $matter->disbursements()->billable()
                    ->when($through, fn ($q) => $q->where('date', '<=', $through))
                    ->orderBy('date')->get()
                : collect(),
            'charges' => ($options['include_charges'] ?? true)
                ? $matter->charges()->billable()
                    ->when($through, fn ($q) => $q->where('date', '<=', $through))
                    ->orderBy('date')->get()
                : collect(),
        ];
    }

    private function addMatterLines(Invoice $invoice, Matter $matter, array $items, string $currency, int &$sort): void
    {
        $agreement = $matter->effectiveBillingAgreement();
        $timeTotal = 0.0;

        foreach ($items['time'] as $entry) {
            $total = $this->fx->convert((float) $entry->amount, $entry->currency_code, $currency);
            $timeTotal += $total;
            $this->addLine($invoice, $entry, $sort++, sprintf(
                '%s — %s — %s',
                $entry->work_date->format('d M Y'),
                $entry->user->name,
                $entry->narrative ?: 'Professional services'
            ), round($entry->billed_minutes / 60, 2), (float) $entry->rate, $total);
        }

        // Capped fee: never let this matter's time exceed what remains
        // under its cap.
        if ($agreement?->type === AgreementType::Capped && $agreement->cap_amount !== null) {
            $alreadyBilled = (float) $matter->timeEntries()
                ->where('status', BillableStatus::Billed)
                ->whereNotIn('id', $items['time']->pluck('id'))
                ->sum('amount');
            $excess = round($alreadyBilled + $timeTotal - (float) $agreement->cap_amount, 2);

            if ($excess > 0) {
                $invoice->lines()->create([
                    'matter_id' => $matter->id,
                    'description' => sprintf('Fee cap adjustment (fees capped at %s %s)', $currency, number_format((float) $agreement->cap_amount, 2)),
                    'quantity' => 1,
                    'unit_amount' => -$excess,
                    'line_total' => -$excess,
                    'sort_order' => $sort++,
                ]);
            }
        }

        foreach ($items['charges'] as $charge) {
            $total = $this->fx->convert((float) $charge->amount, $charge->currency_code, $currency);
            $this->addLine($invoice, $charge, $sort++, $charge->description, 1, $total, $total);
        }

        foreach ($items['disbursements'] as $disbursement) {
            $total = $this->fx->convert((float) $disbursement->amount, $disbursement->currency_code, $currency);
            $this->addLine($invoice, $disbursement, $sort++, sprintf(
                'Disbursement — %s%s',
                $disbursement->description,
                $disbursement->supplier ? " ({$disbursement->supplier})" : ''
            ), 1, $total, $total);
        }
    }

    private function addLine(Invoice $invoice, Model $billable, int $sort, string $description, float $quantity, float $unitAmount, float $total): void
    {
        $line = $invoice->lines()->create([
            'matter_id' => $billable->matter_id,
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'line_total' => $total,
            'sort_order' => $sort,
        ]);

        $billable->update([
            'status' => BillableStatus::Billed,
            'invoice_line_id' => $line->id,
        ]);
    }
}
