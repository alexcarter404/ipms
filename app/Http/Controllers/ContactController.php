<?php

namespace App\Http\Controllers;

use App\Actions\Clients\SaveContact;
use App\Http\Requests\ContactRequest;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

class ContactController extends Controller
{
    public function store(ContactRequest $request, Client $client, SaveContact $action): RedirectResponse
    {
        $action->create($client, $request->validated());

        return back()->with('success', 'Contact added.');
    }

    public function update(ContactRequest $request, Contact $contact, SaveContact $action): RedirectResponse
    {
        $action->update($contact, $request->validated());

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }
}
