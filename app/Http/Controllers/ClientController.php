<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\DeleteClient;
use App\Actions\Clients\UpdateClient;
use App\Enums\ContactType;
use App\Exceptions\DomainActionException;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Repositories\BillingSettingsRepository;
use App\Repositories\ClientRepository;
use App\Support\Countries;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(private ClientRepository $clients)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Clients/Index', [
            'clients' => $this->clients->paginateSearch($request->input('search')),
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'countries' => Countries::options(),
        ]);
    }

    public function store(ClientRequest $request, CreateClient $action): RedirectResponse
    {
        $client = $action->handle($request->validated());

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created.');
    }

    public function show(Client $client, BillingSettingsRepository $billingSettings): Response
    {
        return Inertia::render('Clients/Show', [
            'client' => $this->clients->loadForDisplay($client),
            'countries' => Countries::options(),
            'contactTypes' => ContactType::options(),
            'matters' => $this->clients->paginateMatters($client),
            'billingCurrencies' => Currencies::options(),
            'taxRates' => $billingSettings->taxRateOptions(),
        ]);
    }

    public function edit(Client $client): Response
    {
        $client->load('contacts');

        return Inertia::render('Clients/Edit', [
            'client' => $client,
            'countries' => Countries::options(),
        ]);
    }

    public function update(ClientRequest $request, Client $client, UpdateClient $action): RedirectResponse
    {
        $action->handle($client, $request->validated());

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client updated.');
    }

    public function destroy(Client $client, DeleteClient $action): RedirectResponse
    {
        try {
            $action->handle($client);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }
}
