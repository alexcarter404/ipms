<?php

namespace App\Http\Controllers;

use App\Actions\Entities\CreateClientEntity;
use App\Actions\Entities\DeleteClientEntity;
use App\Actions\Entities\UpdateClientEntity;
use App\Exceptions\DomainActionException;
use App\Http\Requests\ClientEntityRequest;
use App\Models\Client;
use App\Models\ClientEntity;
use Illuminate\Http\RedirectResponse;

class ClientEntityController extends Controller
{
    public function store(ClientEntityRequest $request, Client $client, CreateClientEntity $action): RedirectResponse
    {
        $action->handle($client, $request->validated());

        return back()->with('success', 'Entity added.');
    }

    public function update(ClientEntityRequest $request, ClientEntity $entity, UpdateClientEntity $action): RedirectResponse
    {
        $action->handle($entity, $request->validated());

        return back()->with('success', 'Entity updated.');
    }

    public function destroy(ClientEntity $entity, DeleteClientEntity $action): RedirectResponse
    {
        try {
            $action->handle($entity);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Entity removed.');
    }
}
