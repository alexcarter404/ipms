<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RenewalStatus;
use App\Exceptions\DomainActionException;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Renewal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's window onto their own portfolio: matters, renewals
 * (with pay/abandon instructions feeding the renewals pipeline),
 * documents and invoices — scoped hard to the portal user's client.
 */
class PortalController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Portal/Login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('portal')->attempt($credentials, true)) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();
        $request->user('portal')->update(['last_login_at' => now()]);

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    public function dashboard(Request $request): Response
    {
        $client = $request->user('portal')->client;
        $matterIds = $client->matters()->pluck('id');

        return Inertia::render('Portal/Dashboard', [
            'clientName' => $client->name,
            'matters' => $client->matters()
                ->orderBy('reference')
                ->get()
                ->map(fn ($matter) => [
                    'id' => $matter->id,
                    'reference' => $matter->reference,
                    'title' => $matter->title,
                    'type' => $matter->matter_type->value,
                    'country' => $matter->country_code,
                    'status' => $matter->status->value,
                    'application_no' => $matter->application_no,
                    'registration_no' => $matter->registration_no,
                ]),
            'renewals' => Renewal::with('matter:id,reference,title,country_code')
                ->whereIn('matter_id', $matterIds)
                ->whereIn('status', [RenewalStatus::Upcoming, RenewalStatus::ReminderSent])
                ->orderBy('due_date')
                ->limit(25)
                ->get()
                ->map(fn ($renewal) => [
                    'id' => $renewal->id,
                    'matter_reference' => $renewal->matter->reference,
                    'country' => $renewal->matter->country_code,
                    'cycle' => $renewal->cycle,
                    'due_date' => $renewal->due_date->toDateString(),
                    'official_fee' => $renewal->official_fee,
                    'currency' => $renewal->currency,
                ]),
            'documents' => Document::with('matter:id,reference')
                ->whereIn('matter_id', $matterIds)
                ->current()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'matter_reference' => $document->matter->reference,
                    'category' => $document->category->label(),
                    'created_at' => $document->created_at->toDateString(),
                ]),
            'invoices' => \App\Models\Invoice::with('entity:id,name')
                ->whereIn('status', ['issued', 'paid'])
                ->whereHas('entity', fn ($q) => $q->where('client_id', $client->id))
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn ($invoice) => [
                    'number' => $invoice->number,
                    'entity' => $invoice->entity?->name,
                    'status' => $invoice->status->value,
                    'currency' => $invoice->currency_code,
                    'total' => $invoice->total,
                    'balance' => $invoice->balance(),
                    'issued_at' => $invoice->issued_at?->toDateString(),
                ]),
        ]);
    }

    /** The client's word on a renewal: pay it or let it lapse. */
    public function instructRenewal(Request $request, Renewal $renewal): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:pay,abandon']]);
        $client = $request->user('portal')->client;

        abort_unless($renewal->matter->client_id === $client->id, 403);

        if (! in_array($renewal->status, [RenewalStatus::Upcoming, RenewalStatus::ReminderSent], true)) {
            return back()->with('error', 'This renewal has already been dealt with.');
        }

        $renewal->update($data['decision'] === 'pay'
            ? ['status' => RenewalStatus::Instructed, 'instructed_at' => now()]
            : ['status' => RenewalStatus::Waived]);

        return back()->with('success', $data['decision'] === 'pay'
            ? "Payment instructed for {$renewal->matter->reference} — the firm will handle it."
            : "Noted — {$renewal->matter->reference} will be allowed to lapse.");
    }

    public function downloadDocument(Request $request, Document $document): StreamedResponse
    {
        abort_unless(
            $document->matter->client_id === $request->user('portal')->client_id,
            403
        );
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->filename);
    }
}
