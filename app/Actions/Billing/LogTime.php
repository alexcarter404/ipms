<?php

namespace App\Actions\Billing;

use App\Enums\BillableStatus;
use App\Models\Matter;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\RateResolver;
use Illuminate\Support\Carbon;

class LogTime
{
    public function __construct(private RateResolver $rates, private ExchangeRateService $fx)
    {
    }

    public function handle(Matter $matter, array $data): TimeEntry
    {
        $user = User::findOrFail($data['user_id']);
        $workDate = Carbon::parse($data['work_date']);

        $increment = $matter->effectiveBillingAgreement()?->increment_minutes
            ?? config('billing.default_increment_minutes');
        $billedMinutes = (int) (ceil($data['minutes'] / $increment) * $increment);

        $rate = $data['rate'] ?? $this->rates->resolve(
            $matter, $user, $workDate, $data['activity_code_id'] ?? null
        );
        $status = BillableStatus::from($data['status'] ?? BillableStatus::Billable->value);
        $currency = $matter->billingCurrency();
        $amount = round($billedMinutes / 60 * $rate, 2);

        return $matter->timeEntries()->create([
            'user_id' => $user->id,
            'activity_code_id' => $data['activity_code_id'] ?? null,
            'work_date' => $workDate,
            'minutes' => $data['minutes'],
            'billed_minutes' => $billedMinutes,
            'rate' => $rate,
            'currency_code' => $currency,
            'amount' => $amount,
            'base_amount' => $this->fx->toBase($amount, $currency, $workDate),
            'narrative' => $data['narrative'] ?? null,
            'status' => $status,
        ]);
    }
}
