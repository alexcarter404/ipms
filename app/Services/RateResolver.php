<?php

namespace App\Services;

use App\Enums\AgreementType;
use App\Exceptions\DomainActionException;
use App\Models\Matter;
use App\Models\RateCard;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resolves the hourly rate for a timekeeper on a matter, in the matter's
 * billing currency. A blended agreement overrides everything; otherwise
 * rate rules are matched on up to five dimensions — timekeeper, grade
 * (role), client, matter type, activity code — where a null dimension
 * matches anything. The most specific matching rule wins (personal
 * rates beat grade rates beat client/matter/activity scoping); ties go
 * to the most recent effective date.
 */
class RateResolver
{
    public function __construct(private ExchangeRateService $fx) {}

    public function resolve(Matter $matter, User $user, ?CarbonInterface $date = null, ?int $activityCodeId = null): float
    {
        $date ??= Carbon::today();
        $currency = $matter->billingCurrency();
        $agreement = $matter->effectiveBillingAgreement();

        if ($agreement?->type === AgreementType::Blended && $agreement->blended_rate !== null) {
            return (float) $agreement->blended_rate;
        }

        $card = RateCard::where('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->where(fn ($q) => $q->whereNull('role')->when(
                $user->role,
                fn ($qq) => $qq->orWhere('role', $user->role),
            ))
            ->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $matter->client_id))
            ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
            ->where(fn ($q) => $q->whereNull('activity_code_id')->when(
                $activityCodeId,
                fn ($qq) => $qq->orWhere('activity_code_id', $activityCodeId),
            ))
            ->get()
            ->sortByDesc(fn (RateCard $c) => [
                $c->specificity(),
                $c->effective_from->timestamp,
            ])
            ->first();

        if (! $card) {
            throw new DomainActionException(
                "No rate rule covers {$user->name} on this matter. Add one in Billing Settings."
            );
        }

        return $this->fx->convert((float) $card->hourly_rate, $card->currency_code, $currency, $date);
    }
}
