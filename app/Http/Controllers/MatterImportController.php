<?php

namespace App\Http\Controllers;

use App\Actions\Integrations\ImportMatterFromOffice;
use App\Exceptions\DomainActionException;
use App\Models\Client;
use App\Models\RegisterCheck;
use App\Services\Integrations\RegisterReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatterImportController extends Controller
{
    public function store(Request $request, ImportMatterFromOffice $action): RedirectResponse
    {
        $data = $request->validate([
            'office' => ['required', Rule::in(array_keys(config('integrations.offices')))],
            'application_no' => ['required', 'string', 'max:60'],
            'client_id' => ['required', 'exists:clients,id'],
        ]);

        try {
            $matter = $action->handle(
                $data['office'],
                $data['application_no'],
                Client::findOrFail($data['client_id']),
                $request->user()
            );
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('matters.show', $matter)
            ->with('success', "Imported {$matter->reference} from the {$data['office']} register.");
    }

    public function reconcile(RegisterReconciliation $reconciliation): RedirectResponse
    {
        $stats = $reconciliation->run();

        return back()->with('success',
            "Reconciled {$stats['checked']} matter(s) against the registers — {$stats['drift']} drifted.");
    }

    public function accept(RegisterCheck $registerCheck, RegisterReconciliation $reconciliation): RedirectResponse
    {
        if ($registerCheck->status !== 'drift' || $registerCheck->resolved_at) {
            return back()->with('error', 'This check has nothing left to apply.');
        }

        $matter = $reconciliation->acceptOfficeValues($registerCheck);

        return back()->with('success', "Applied the register values to {$matter->reference}.");
    }

    public function dismiss(RegisterCheck $registerCheck): RedirectResponse
    {
        $registerCheck->update(['resolved_at' => now()]);

        return back()->with('success', 'Check dismissed — our record stands.');
    }
}
