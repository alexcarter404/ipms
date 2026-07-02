<?php

namespace App\Http\Controllers;

use App\Actions\Matters\AttachParty;
use App\Actions\Matters\DetachParty;
use App\Enums\PartyRole;
use App\Exceptions\DomainActionException;
use App\Http\Requests\MatterPartyRequest;
use App\Models\Matter;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatterPartyController extends Controller
{
    public function store(MatterPartyRequest $request, Matter $matter, AttachParty $action): RedirectResponse
    {
        try {
            $action->handle($matter, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Party added.');
    }

    public function destroy(Request $request, Matter $matter, Party $party, DetachParty $action): RedirectResponse
    {
        $request->validate(['role' => ['required', Rule::enum(PartyRole::class)]]);

        $action->handle($matter, $party, $request->input('role'));

        return back()->with('success', 'Party removed.');
    }
}
