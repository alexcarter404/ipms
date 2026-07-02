<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientEntityController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($client, $data) {
            $entity = $client->entities()->create($data);

            // First entity is always the default; an explicit request wins.
            if (($data['is_default'] ?? false) || $client->entities()->count() === 1) {
                $entity->makeDefault();
            }
        });

        return back()->with('success', 'Entity added.');
    }

    public function update(Request $request, ClientEntity $entity): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($entity, $data) {
            // The default flag is only ever granted here; to remove it,
            // make another entity the default.
            $makeDefault = $data['is_default'] ?? false;
            unset($data['is_default']);

            $entity->update($data);

            if ($makeDefault) {
                $entity->makeDefault();
            }
        });

        return back()->with('success', 'Entity updated.');
    }

    public function destroy(ClientEntity $entity): RedirectResponse
    {
        if ($entity->matters()->exists()) {
            return back()->with('error', 'This entity is the billing entity on matters — reassign them first.');
        }

        if ($entity->is_default) {
            return back()->with('error', 'The default entity cannot be deleted — make another entity the default first.');
        }

        $entity->delete();

        return back()->with('success', 'Entity removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'billing_reference' => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
