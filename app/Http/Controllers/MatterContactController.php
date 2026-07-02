<?php

namespace App\Http\Controllers;

use App\Actions\Matters\LinkContact;
use App\Actions\Matters\UnlinkContact;
use App\Enums\MatterContactRole;
use App\Exceptions\DomainActionException;
use App\Http\Requests\MatterContactRequest;
use App\Models\Contact;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatterContactController extends Controller
{
    public function store(MatterContactRequest $request, Matter $matter, LinkContact $action): RedirectResponse
    {
        try {
            $action->handle($matter, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Contact linked.');
    }

    public function destroy(Request $request, Matter $matter, Contact $contact, UnlinkContact $action): RedirectResponse
    {
        $request->validate(['role' => ['required', Rule::enum(MatterContactRole::class)]]);

        $action->handle($matter, $contact, $request->input('role'));

        return back()->with('success', 'Contact unlinked.');
    }
}
