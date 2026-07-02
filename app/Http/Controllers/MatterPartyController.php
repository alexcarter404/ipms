<?php

namespace App\Http\Controllers;

use App\Enums\PartyRole;
use App\Models\Matter;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatterPartyController extends Controller
{
    /** Attach an existing party, or create and attach a new one. */
    public function store(Request $request, Matter $matter): RedirectResponse
    {
        $data = $request->validate([
            'party_id' => ['nullable', 'exists:parties,id', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:255', 'required_without:party_id'],
            'party_type' => ['nullable', Rule::in(['individual', 'organisation'])],
            'role' => ['required', Rule::enum(PartyRole::class)],
        ]);

        $partyId = $data['party_id']
            ?? Party::create([
                'name' => $data['name'],
                'type' => $data['party_type'] ?? 'individual',
            ])->id;

        $exists = $matter->parties()
            ->wherePivot('party_id', $partyId)
            ->wherePivot('role', $data['role'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'That party already has this role on the matter.');
        }

        $matter->parties()->attach($partyId, [
            'role' => $data['role'],
            'sort_order' => $matter->parties()->wherePivot('role', $data['role'])->count(),
        ]);

        return back()->with('success', 'Party added.');
    }

    public function destroy(Request $request, Matter $matter, Party $party): RedirectResponse
    {
        $request->validate(['role' => ['required', Rule::enum(PartyRole::class)]]);

        $matter->parties()
            ->wherePivot('role', $request->input('role'))
            ->detach($party->id);

        return back()->with('success', 'Party removed.');
    }
}
