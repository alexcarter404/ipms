<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\MatterContactRole;
use App\Models\Contact;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatterContactController extends Controller
{
    /**
     * Link an existing contact of the matter's client, or create a new
     * contact on the client and link it — in a given role.
     */
    public function store(Request $request, Matter $matter): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => [
                'nullable', 'required_without:name',
                Rule::exists('contacts', 'id')->where('client_id', $matter->client_id),
            ],
            'name' => ['nullable', 'string', 'max:255', 'required_without:contact_id'],
            'contact_type' => ['nullable', Rule::enum(ContactType::class)],
            'email' => ['nullable', 'email', 'max:255', 'required_if:contact_type,mailbox'],
            'role' => ['required', Rule::enum(MatterContactRole::class)],
        ]);

        $contactId = $data['contact_id']
            ?? $matter->client->contacts()->create([
                'name' => $data['name'],
                'type' => $data['contact_type'] ?? ContactType::Person,
                'email' => $data['email'] ?? null,
            ])->id;

        $exists = $matter->contacts()
            ->wherePivot('contact_id', $contactId)
            ->wherePivot('role', $data['role'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'That contact already has this role on the matter.');
        }

        $matter->contacts()->attach($contactId, ['role' => $data['role']]);

        return back()->with('success', 'Contact linked.');
    }

    public function destroy(Request $request, Matter $matter, Contact $contact): RedirectResponse
    {
        $request->validate(['role' => ['required', Rule::enum(MatterContactRole::class)]]);

        $matter->contacts()
            ->wherePivot('role', $request->input('role'))
            ->detach($contact->id);

        return back()->with('success', 'Contact unlinked.');
    }
}
