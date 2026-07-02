<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunicationController extends Controller
{
    public function store(Request $request, Matter $matter): RedirectResponse
    {
        $data = $request->validate([
            'comm_template_id' => ['nullable', 'exists:comm_templates,id'],
            'channel' => ['required', Rule::in(['email', 'letter'])],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $matter->communications()->create($data + [
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Communication saved as draft.');
    }

    /** Mark a draft as sent (records the point it left the system). */
    public function markSent(Communication $communication): RedirectResponse
    {
        if ($communication->status === 'sent') {
            return back()->with('error', 'Communication already sent.');
        }

        $communication->update(['status' => 'sent', 'sent_at' => now()]);

        return back()->with('success', 'Communication marked as sent.');
    }

    public function destroy(Communication $communication): RedirectResponse
    {
        if ($communication->status === 'sent') {
            return back()->with('error', 'Sent communications cannot be deleted.');
        }

        $communication->delete();

        return back()->with('success', 'Draft deleted.');
    }
}
