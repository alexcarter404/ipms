<?php

namespace App\Repositories;

use App\Models\ActivityCode;
use App\Models\ExchangeRate;
use App\Models\RateCard;
use App\Models\TaxRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Billing reference data: tax rates, exchange rates, activity codes,
 * rate cards.
 */
class BillingSettingsRepository
{
    public function taxRates(): Collection
    {
        return TaxRate::orderByDesc('is_default')->orderBy('name')->get();
    }

    public function taxRateOptions(): array
    {
        return $this->taxRates()
            ->map(fn (TaxRate $rate) => [
                'value' => $rate->id,
                'label' => sprintf('%s (%s%%)', $rate->name, rtrim(rtrim((string) $rate->rate, '0'), '.')),
            ])
            ->all();
    }

    /** The latest stored rate per currency. */
    public function latestExchangeRates(): Collection
    {
        return ExchangeRate::orderByDesc('rate_date')
            ->get()
            ->unique('currency_code')
            ->sortBy('currency_code')
            ->values();
    }

    public function activityCodes(): Collection
    {
        return ActivityCode::orderBy('code')->get();
    }

    public function activityCodeOptions(): array
    {
        return $this->activityCodes()
            ->map(fn (ActivityCode $code) => [
                'value' => $code->id,
                'label' => "{$code->code} — {$code->description}",
            ])
            ->all();
    }

    /**
     * Rate rules for the settings DataTable: searchable, filterable,
     * sortable, paginated — ordered most-specific first by default.
     */
    public function paginateRateCards(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['hourly_rate', 'effective_from'], true)
            ? $filters['sort']
            : null;
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return RateCard::query()
            ->with(['user:id,name', 'client:id,name', 'activityCode:id,code'])
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = "%{$term}%";
                $q->where(fn ($w) => $w
                    ->whereHas('user', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('activityCode', fn ($a) => $a->where('code', 'like', $like))
                    ->orWhere('role', 'like', $like));
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['matter_type'] ?? null, fn ($q, $type) => $q->where('matter_type', $type))
            ->when($sort,
                fn ($q) => $q->orderBy($sort, $dir),
                fn ($q) => $q->orderByRaw(
                    '(case when user_id is not null then 16 else 0 end'
                    .' + case when role is not null then 8 else 0 end'
                    .' + case when client_id is not null then 4 else 0 end'
                    .' + case when matter_type is not null then 2 else 0 end'
                    .' + case when activity_code_id is not null then 1 else 0 end) desc, effective_from desc'
                )
            )
            ->paginate($perPage, ['*'], 'rr_page')
            ->withQueryString();
    }
}
