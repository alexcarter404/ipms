<?php

namespace App\Repositories;

use App\Enums\AgreementType;
use App\Enums\BillableStatus;
use App\Models\ClientEntity;
use App\Models\Matter;
use App\Services\ExchangeRateService;
use App\Support\MoneyMinor;
use Illuminate\Database\Eloquent\Collection;

/**
 * Work-in-progress queries: the unbilled value sitting on matters.
 */
class WipRepository
{
    public function __construct(private ExchangeRateService $fx) {}

    /**
     * The WIP dashboard's compact top level: one row per billing entity
     * with its unbilled total, matter count, and the age of its oldest
     * unbilled item.
     *
     * @return list<array>
     */
    public function entitySummary(array $filters = []): array
    {
        return $this->mattersWithWip($filters)
            ->groupBy(fn (Matter $matter) => $matter->effectiveBillingEntity()->id)
            ->map(function ($group) {
                $entity = $group->first()->effectiveBillingEntity();
                $currency = $entity->currency_code ?? config('billing.base_currency');

                $total = round($group->sum(fn (Matter $matter) => $this->fx->convert(
                    $this->billableTotal($matter), $matter->billingCurrency(), $currency
                )), 2);

                $oldest = $group
                    ->map(fn (Matter $matter) => collect([
                        $matter->oldest_time, $matter->oldest_disbursement, $matter->oldest_charge,
                    ])->filter()->map(fn ($date) => substr($date, 0, 10))->min())
                    ->filter()
                    ->min();

                return [
                    'entity' => [
                        'id' => $entity->id,
                        'name' => $entity->name,
                        'client_name' => $group->first()->client->name,
                        'currency' => $currency,
                    ],
                    'matter_count' => $group->count(),
                    'total' => $total,
                    'base_total' => round($group->sum(fn (Matter $matter) => $this->billableBaseTotal($matter)), 2),
                    'oldest_date' => $oldest,
                    'oldest_days' => $oldest ? (int) floor(now()->diffInDays($oldest, true)) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * The drill-in for one entity: every matter with unbilled work and
     * its individual WIP items, ready to review, amend and bill.
     */
    public function entityWip(ClientEntity $entity): array
    {
        $matters = $this->mattersWithWip()
            ->filter(fn (Matter $matter) => $matter->effectiveBillingEntity()->id === $entity->id)
            ->values();

        $currency = $entity->currency_code ?? config('billing.base_currency');

        $rows = $matters->map(function (Matter $matter) {
            $billable = fn ($q) => $q->where('status', BillableStatus::Billable);
            $billsTime = ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->billsTime();

            $items = collect()
                ->concat($matter->timeEntries()->tap($billable)->with(['user:id,name', 'activityCode:id,code'])->get()
                    ->map(fn ($entry) => [
                        'kind' => 'time',
                        'id' => $entry->id,
                        'date' => $entry->work_date->toDateString(),
                        'description' => $entry->narrative ?: 'Professional services',
                        'meta' => trim(sprintf('%s · %dm%s',
                            $entry->user->name,
                            $entry->billed_minutes,
                            $entry->activityCode ? " · {$entry->activityCode->code}" : ''
                        )),
                        'amount' => (float) $entry->amount,
                        'currency' => $entry->currency_code,
                        'billed' => $billsTime,
                    ]))
                ->concat($matter->disbursements()->tap($billable)->get()
                    ->map(fn ($item) => [
                        'kind' => 'disbursement',
                        'id' => $item->id,
                        'date' => $item->date->toDateString(),
                        'description' => $item->description,
                        'meta' => trim(sprintf('%s%s%% markup',
                            $item->supplier ? "{$item->supplier} · " : '',
                            (float) $item->markup_pct
                        )),
                        'amount' => (float) $item->amount,
                        'currency' => $item->currency_code,
                        'billed' => true,
                    ]))
                ->concat($matter->charges()->tap($billable)->get()
                    ->map(fn ($charge) => [
                        'kind' => 'charge',
                        'id' => $charge->id,
                        'date' => $charge->date->toDateString(),
                        'description' => $charge->description,
                        'meta' => $charge->type->label(),
                        'amount' => (float) $charge->amount,
                        'currency' => $charge->currency_code,
                        'billed' => true,
                    ]))
                ->sortBy('date')
                ->values();

            return [
                'id' => $matter->id,
                'reference' => $matter->reference,
                'title' => $matter->title,
                'agreement' => ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->label(),
                'bills_time' => $billsTime,
                'currency' => $matter->billingCurrency(),
                'billable_total' => $this->billableTotal($matter),
                'items' => $items->all(),
            ];
        })->values();

        return [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'client_id' => $entity->client_id,
                'client_name' => $entity->client->name,
                'currency' => $currency,
            ],
            'matters' => $rows->all(),
            'total' => round($matters->sum(fn (Matter $matter) => $this->fx->convert(
                $this->billableTotal($matter), $matter->billingCurrency(), $currency
            )), 2),
            'total_base' => round($matters->sum(fn (Matter $matter) => $this->billableBaseTotal($matter)), 2),
        ];
    }

    /** Matters carrying unbilled WIP, with sums and oldest-item dates. */
    private function mattersWithWip(array $filters = []): Collection
    {
        $billable = fn ($q) => $q->where('status', BillableStatus::Billable);

        return Matter::query()
            ->with(['client:id,name', 'billingEntity.billingAgreement', 'client.entities.billingAgreement', 'billingAgreement'])
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('responsible_user_id', $id))
            ->where(fn ($q) => $q
                ->whereHas('timeEntries', $billable)
                ->orWhereHas('disbursements', $billable)
                ->orWhereHas('charges', $billable))
            ->withSum(['timeEntries as wip_time' => $billable], 'amount')
            ->withSum(['disbursements as wip_disbursements' => $billable], 'amount')
            ->withSum(['charges as wip_charges' => $billable], 'amount')
            ->withSum(['timeEntries as wip_time_base' => $billable], 'base_amount')
            ->withSum(['disbursements as wip_disbursements_base' => $billable], 'base_amount')
            ->withSum(['charges as wip_charges_base' => $billable], 'base_amount')
            ->withMin(['timeEntries as oldest_time' => $billable], 'work_date')
            ->withMin(['disbursements as oldest_disbursement' => $billable], 'date')
            ->withMin(['charges as oldest_charge' => $billable], 'date')
            ->orderBy('reference')
            ->get()
            ->filter(fn (Matter $matter) => $matter->effectiveBillingEntity() !== null);
    }

    /** What invoicing would pull: time only when the agreement bills it. */
    private function billableTotal(Matter $matter): float
    {
        $billsTime = ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->billsTime();

        // withSum aliases carry raw minor units — sum as integers, convert once
        return MoneyMinor::fromMinor(
            ($billsTime ? (int) ($matter->wip_time ?? 0) : 0)
            + (int) ($matter->wip_disbursements ?? 0)
            + (int) ($matter->wip_charges ?? 0)
        );
    }

    /** The same total from the base-currency values stored at capture. */
    private function billableBaseTotal(Matter $matter): float
    {
        $billsTime = ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->billsTime();

        return MoneyMinor::fromMinor(
            ($billsTime ? (int) ($matter->wip_time_base ?? 0) : 0)
            + (int) ($matter->wip_disbursements_base ?? 0)
            + (int) ($matter->wip_charges_base ?? 0)
        );
    }

    /** @return array{time: float, disbursements: float, charges: float, total: float, currency: string} */
    public function totals(Matter $matter): array
    {
        $time = MoneyMinor::fromMinor($matter->timeEntries()->billable()->sum('amount'));
        $disbursements = MoneyMinor::fromMinor($matter->disbursements()->billable()->sum('amount'));
        $charges = MoneyMinor::fromMinor($matter->charges()->billable()->sum('amount'));
        $baseTotal = MoneyMinor::fromMinor(
            (int) $matter->timeEntries()->billable()->sum('base_amount')
            + (int) $matter->disbursements()->billable()->sum('base_amount')
            + (int) $matter->charges()->billable()->sum('base_amount')
        );

        return [
            'time' => $time,
            'disbursements' => $disbursements,
            'charges' => $charges,
            'total' => round($time + $disbursements + $charges, 2),
            'currency' => $matter->billingCurrency(),
            'base_total' => round($baseTotal, 2),
            'base_currency' => config('billing.base_currency'),
        ];
    }

    /** Everything recorded against the matter, for the billing tab. */
    public function forMatter(Matter $matter): array
    {
        return [
            'timeEntries' => $matter->timeEntries()
                ->with(['user:id,name', 'activityCode:id,code,description'])
                ->limit(100)->get(),
            'disbursements' => $matter->disbursements()->limit(100)->get(),
            'charges' => $matter->charges()->with('stage:id,description')->limit(100)->get(),
            'invoices' => $matter->invoices()->with('entity:id,name')->get(),
        ];
    }
}
