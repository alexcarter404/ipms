<?php

namespace App\Repositories;

use App\Enums\AgreementType;
use App\Enums\BillableStatus;
use App\Models\Matter;
use App\Services\ExchangeRateService;

/**
 * Work-in-progress queries: the unbilled value sitting on a matter.
 */
class WipRepository
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    /**
     * Firm-wide unbilled WIP, grouped by the billing entity each matter
     * bills to — the shape the WIP dashboard renders and bills from.
     *
     * @return list<array{entity: array, matters: list<array>, total: float}>
     */
    public function summary(array $filters = []): array
    {
        $billable = fn ($q) => $q->where('status', BillableStatus::Billable);

        $matters = Matter::query()
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
            ->orderBy('reference')
            ->get();

        return $matters
            ->filter(fn (Matter $matter) => $matter->effectiveBillingEntity() !== null)
            ->groupBy(fn (Matter $matter) => $matter->effectiveBillingEntity()->id)
            ->map(function ($group) {
                $entity = $group->first()->effectiveBillingEntity();
                $currency = $entity->currency_code ?? config('billing.base_currency');

                $rows = $group->map(function (Matter $matter) use ($currency) {
                    $billsTime = ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->billsTime();
                    $time = (float) ($matter->wip_time ?? 0);
                    $disbursements = (float) ($matter->wip_disbursements ?? 0);
                    $charges = (float) ($matter->wip_charges ?? 0);
                    $billableTotal = round(($billsTime ? $time : 0) + $disbursements + $charges, 2);

                    return [
                        'id' => $matter->id,
                        'reference' => $matter->reference,
                        'title' => $matter->title,
                        'agreement' => ($matter->effectiveBillingAgreement()?->type ?? AgreementType::Hourly)->label(),
                        'bills_time' => $billsTime,
                        'currency' => $matter->billingCurrency(),
                        'time' => $time,
                        'disbursements' => $disbursements,
                        'charges' => $charges,
                        'billable_total' => $billableTotal,
                        // Group totals need one currency — the entity's
                        'billable_in_entity_currency' => $this->fx->convert(
                            $billableTotal, $matter->billingCurrency(), $currency
                        ),
                    ];
                })->values();

                return [
                    'entity' => [
                        'id' => $entity->id,
                        'name' => $entity->name,
                        'client_name' => $group->first()->client->name,
                        'currency' => $currency,
                    ],
                    'matters' => $rows->all(),
                    'total' => round($rows->sum('billable_in_entity_currency'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
    /** @return array{time: float, disbursements: float, charges: float, total: float, currency: string} */
    public function totals(Matter $matter): array
    {
        $time = (float) $matter->timeEntries()->billable()->sum('amount');
        $disbursements = (float) $matter->disbursements()->billable()->sum('amount');
        $charges = (float) $matter->charges()->billable()->sum('amount');

        return [
            'time' => $time,
            'disbursements' => $disbursements,
            'charges' => $charges,
            'total' => round($time + $disbursements + $charges, 2),
            'currency' => $matter->billingCurrency(),
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
