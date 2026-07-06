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
 * the most specific rate card wins: user+client, then user, then client
 * (its own blended rate), then the firm-wide default.
 */
class RateResolver
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    public function resolve(Matter $matter, User $user, ?CarbonInterface $date = null): float
    {
        $date ??= Carbon::today();
        $currency = $matter->billingCurrency();
        $agreement = $matter->effectiveBillingAgreement();

        if ($agreement?->type === AgreementType::Blended && $agreement->blended_rate !== null) {
            return (float) $agreement->blended_rate;
        }

        $card = RateCard::where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($user, $matter) {
                $q->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->where(function ($q) use ($matter) {
                $q->whereNull('client_id')->orWhere('client_id', $matter->client_id);
            })
            ->get()
            ->sortByDesc(fn (RateCard $c) => [
                ($c->user_id ? 2 : 0) + ($c->client_id ? 1 : 0), // specificity
                $c->effective_from->timestamp,                    // recency
            ])
            ->first();

        if (! $card) {
            throw new DomainActionException(
                "No rate card covers {$user->name} on this matter. Add one in Billing Settings."
            );
        }

        return $this->fx->convert((float) $card->hourly_rate, $card->currency_code, $currency, $date);
    }
}
