<?php

namespace App\Http\Controllers;

use App\Actions\Billing\SaveTaxRate;
use App\Exceptions\DomainActionException;
use App\Http\Requests\ActivityCodeRequest;
use App\Http\Requests\ExchangeRateRequest;
use App\Http\Requests\RateCardRequest;
use App\Http\Requests\TaxRateRequest;
use App\Models\ActivityCode;
use App\Models\ExchangeRate;
use App\Models\RateCard;
use App\Models\TaxRate;
use App\Repositories\BillingSettingsRepository;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Services\ExchangeRateSync;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingsController extends Controller
{
    public function __construct(private BillingSettingsRepository $settings)
    {
    }

    public function edit(UserRepository $users, ClientRepository $clients): Response
    {
        return Inertia::render('Billing/Settings', [
            'baseCurrency' => Currencies::base(),
            'currencies' => Currencies::options(),
            'exchangeRates' => $this->settings->latestExchangeRates(),
            'taxRates' => $this->settings->taxRates(),
            'activityCodes' => $this->settings->activityCodes(),
            'rateCards' => $this->settings->rateCards(),
            'users' => $users->options(),
            'clients' => $clients->options(),
        ]);
    }

    public function syncRates(ExchangeRateSync $sync): RedirectResponse
    {
        try {
            $result = $sync->sync();
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Synced %d exchange rates for %s.', count($result['rates']), $result['date']
        ));
    }

    public function saveExchangeRate(ExchangeRateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        ExchangeRate::updateOrCreate(
            ['currency_code' => $data['currency_code'], 'rate_date' => $data['rate_date']],
            ['rate' => $data['rate']]
        );

        return back()->with('success', "Rate saved for {$data['currency_code']}.");
    }

    public function saveTaxRate(TaxRateRequest $request, SaveTaxRate $action, ?TaxRate $taxRate = null): RedirectResponse
    {
        $action->handle($request->validated(), $taxRate);

        return back()->with('success', 'Tax rate saved.');
    }

    public function deleteTaxRate(TaxRate $taxRate): RedirectResponse
    {
        $taxRate->delete();

        return back()->with('success', 'Tax rate deleted.');
    }

    public function saveActivityCode(ActivityCodeRequest $request, ?ActivityCode $activityCode = null): RedirectResponse
    {
        $activityCode
            ? $activityCode->update($request->validated())
            : ActivityCode::create($request->validated());

        return back()->with('success', 'Activity code saved.');
    }

    public function deleteActivityCode(ActivityCode $activityCode): RedirectResponse
    {
        $activityCode->delete();

        return back()->with('success', 'Activity code deleted.');
    }

    public function saveRateCard(RateCardRequest $request, ?RateCard $rateCard = null): RedirectResponse
    {
        $rateCard
            ? $rateCard->update($request->validated())
            : RateCard::create($request->validated());

        return back()->with('success', 'Rate card saved.');
    }

    public function deleteRateCard(RateCard $rateCard): RedirectResponse
    {
        $rateCard->delete();

        return back()->with('success', 'Rate card deleted.');
    }
}
