<?php

namespace App\Http\Controllers;

use App\Actions\Billing\SaveQuote;
use App\Actions\Billing\TransitionQuote;
use App\Enums\QuoteStatus;
use App\Exceptions\DomainActionException;
use App\Http\Requests\QuoteRequest;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Repositories\BillingSettingsRepository;
use App\Repositories\ClientRepository;
use App\Repositories\MatterRepository;
use App\Repositories\QuoteRepository;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function __construct(
        private QuoteRepository $quotes,
        private ClientRepository $clients,
        private MatterRepository $matters,
        private BillingSettingsRepository $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only('status', 'client_id');

        return Inertia::render('Billing/Quotes/Index', [
            'quotes' => $this->quotes->paginateFiltered($filters),
            'filters' => $filters,
            'statuses' => QuoteStatus::options(),
            'clients' => $this->clients->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Billing/Quotes/Create', $this->formOptions());
    }

    public function store(QuoteRequest $request, SaveQuote $action): RedirectResponse
    {
        $quote = $action->create($request->validated());

        return redirect()->route('quotes.edit', $quote)
            ->with('success', "Quote {$quote->quote_no} created.");
    }

    public function edit(Quote $quote): Response
    {
        return Inertia::render('Billing/Quotes/Edit', [
            'quote' => $quote->load(['lines', 'client:id,name', 'entity:id,name']),
            // The quote stores a tax snapshot; map it back to the live rate
            // so editing a draft keeps its tax selection.
            'quoteTaxRateId' => $quote->tax_name
                ? TaxRate::where('name', $quote->tax_name)->value('id')
                : null,
        ] + $this->formOptions());
    }

    public function update(QuoteRequest $request, Quote $quote, SaveQuote $action): RedirectResponse
    {
        try {
            $action->update($quote, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Quote updated.');
    }

    public function transition(Request $request, Quote $quote, TransitionQuote $action): RedirectResponse
    {
        $request->validate(['status' => ['required', 'string']]);

        try {
            $action->handle($quote, QuoteStatus::from($request->string('status')->value()));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Quote marked {$quote->fresh()->status->label()}.");
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();

        return redirect()->route('quotes.index')->with('success', 'Quote deleted.');
    }

    private function formOptions(): array
    {
        return [
            'clients' => $this->clients->optionsWithEntities(),
            'matters' => $this->matters->referenceOptions(),
            'currencies' => Currencies::options(),
            'taxRates' => $this->settings->taxRateOptions(),
        ];
    }
}
