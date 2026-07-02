<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $client->contacts()->create($this->validated($request));

        return back()->with('success', 'Contact added.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $contact->update($this->validated($request));

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ContactType::class)],
            'email' => ['nullable', 'email', 'max:255', 'required_if:type,mailbox'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
