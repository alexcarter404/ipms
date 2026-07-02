<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $clients = Client::query()
            ->withCount('matters')
            ->when($request->input('search'), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'countries' => Countries::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created.');
    }

    public function show(Client $client): Response
    {
        $client->load(['contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name')]);

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'matters' => $client->matters()
                ->with('responsibleUser:id,name')
                ->latest()
                ->paginate(10),
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

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request, $client));

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        if ($client->matters()->exists()) {
            return back()->with('error', 'Cannot delete a client with matters on record.');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('clients')->ignore($client)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['company', 'individual'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
