<?php

namespace App\Repositories;

use App\Enums\BillableStatus;
use App\Models\Matter;
use App\Services\ExchangeRateService;

/**
 * Budget-vs-cost queries. A matter's budget accumulates across its
 * budget rows; consumption counts every cost, billed and unbilled
 * (written-off and non-billable items excluded). Comparisons use the
 * base-currency values frozen at capture on both sides.
 */
class BudgetRepository
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    /** The matter billing tab's budget card. */
    public function forMatter(Matter $matter): array
    {
        $matter->loadMissing('budgets.creator');

        return [
            'rows' => $matter->budgets->map(fn ($budget) => [
                'id' => $budget->id,
                'description' => $budget->description,
                'amount' => (float) $budget->amount,
                'currency' => $budget->currency_code,
                'base_amount' => (float) $budget->base_amount,
                'created_by' => $budget->creator?->name,
                'created_at' => $budget->created_at->toDateString(),
                'amended' => $budget->updated_at->gt($budget->created_at),
            ])->all(),
        ] + $this->position($matter);
    }

    /**
     * The budget dashboard: budget-vs-cost per matter across a
     * portfolio, filterable by responsible attorney and client.
     *
     * @return list<array>
     */
    public function portfolio(array $filters = []): array
    {
        return Matter::query()
            ->whereHas('budgets')
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('responsible_user_id', $id))
            ->when($filters['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->with(['client:id,name', 'responsibleUser:id,name', 'budgets',
                'billingEntity.billingAgreement', 'client.entities.billingAgreement', 'billingAgreement'])
            ->orderBy('reference')
            ->get()
            ->map(fn (Matter $matter) => [
                'id' => $matter->id,
                'reference' => $matter->reference,
                'title' => $matter->title,
                'client_name' => $matter->client->name,
                'attorney' => $matter->responsibleUser?->name,
            ] + $this->position($matter))
            ->sortByDesc('utilisation')
            ->values()
            ->all();
    }

    /** Budget vs consumed for one matter, in matter currency and base. */
    private function position(Matter $matter): array
    {
        $currency = $matter->billingCurrency();
        $budgets = $matter->budgets;

        // Matter-currency figures are only meaningful when every budget
        // row was entered in the matter's billing currency (the norm).
        $uniform = $budgets->isNotEmpty() && $budgets->every(
            fn ($budget) => $budget->currency_code === $currency
        );

        $counted = fn ($q) => $q->whereIn('status', [BillableStatus::Billable, BillableStatus::Billed]);

        // Query-level sums return raw minor units; collection sums on
        // loaded models (below) already come through the Money cast.
        $consumed = \App\Support\MoneyMinor::fromMinor(
            (int) $matter->timeEntries()->tap($counted)->sum('amount')
            + (int) $matter->disbursements()->tap($counted)->sum('amount')
            + (int) $matter->charges()->tap($counted)->sum('amount')
        );
        $consumedBase = \App\Support\MoneyMinor::fromMinor(
            (int) $matter->timeEntries()->tap($counted)->sum('base_amount')
            + (int) $matter->disbursements()->tap($counted)->sum('base_amount')
            + (int) $matter->charges()->tap($counted)->sum('base_amount')
        );

        $budgetBase = round((float) $budgets->sum('base_amount'), 2);

        return [
            'currency' => $currency,
            'base_currency' => config('billing.base_currency'),
            'budget' => $uniform ? round((float) $budgets->sum('amount'), 2) : null,
            'budget_base' => $budgetBase,
            'consumed' => round($consumed, 2),
            'consumed_base' => round($consumedBase, 2),
            'utilisation' => $budgetBase > 0
                ? round($consumedBase / $budgetBase * 100, 1)
                : null,
        ];
    }
}
