<?php

namespace App\Http\Controllers;

use App\Actions\Communications\ComposeCommunication;
use App\Actions\Communications\DeleteCommunication;
use App\Actions\Communications\MarkCommunicationSent;
use App\Exceptions\DomainActionException;
use App\Http\Requests\CommunicationRequest;
use App\Models\Communication;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;

class CommunicationController extends Controller
{
    public function store(CommunicationRequest $request, Matter $matter, ComposeCommunication $action): RedirectResponse
    {
        $action->handle($matter, $request->validated(), $request->user());

        return back()->with('success', 'Communication saved as draft.');
    }

    public function markSent(Communication $communication, MarkCommunicationSent $action): RedirectResponse
    {
        try {
            $result = $action->handle($communication);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result['delivered']
            ? "Email sent to {$communication->recipient_email}."
            : 'Communication marked as sent.');
    }

    public function destroy(Communication $communication, DeleteCommunication $action): RedirectResponse
    {
        try {
            $action->handle($communication);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Draft deleted.');
    }
}
