<?php

namespace App\Http\Controllers;

use App\Enums\RenewalStatus;
use App\Models\Matter;
use App\Models\Renewal;
use App\Services\RenewalScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RenewalController extends Controller
{
    public function index(Request $request): Response
    {
        $renewals = Renewal::query()
            ->with('matter:id,reference,title,matter_type,country_code,client_id', 'matter.client:id,name')
            ->when(
                $request->input('status'),
                fn ($q, $status) => $status === 'open' ? $q->open() : $q->where('status', $status),
                fn ($q) => $q->open()
            )
            ->when($request->input('due_within'), fn ($q, $days) => $q->dueWithin((int) $days))
            ->when($request->input('search'), fn ($q, $term) => $q->whereHas(
                'matter',
                fn ($m) => $m->where('reference', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")
            ))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Renewals/Index', [
            'renewals' => $renewals,
            'filters' => $request->only('status', 'due_within', 'search'),
            'statuses' => RenewalStatus::options(),
        ]);
    }

    /** Generate the renewal schedule for a matter from its key dates. */
    public function generate(Matter $matter, RenewalScheduler $scheduler): RedirectResponse
    {
        $created = $scheduler->generate($matter);

        if ($created->isEmpty()) {
            return back()->with('error', 'No renewals generated — check the matter has a filing or registration date, and that a schedule applies to this matter type.');
        }

        return back()->with('success', "{$created->count()} renewal(s) generated.");
    }

    public function store(Request $request, Matter $matter): RedirectResponse
    {
        $data = $request->validate([
            'cycle' => ['required', 'integer', 'min:1', Rule::unique('renewals')->where('matter_id', $matter->id)],
            'due_date' => ['required', 'date'],
            'grace_date' => ['nullable', 'date', 'after_or_equal:due_date'],
            'official_fee' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $matter->renewals()->create($data + ['status' => RenewalStatus::Upcoming]);

        return back()->with('success', 'Renewal added.');
    }

    public function update(Request $request, Renewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(RenewalStatus::class)],
            'due_date' => ['sometimes', 'date'],
            'grace_date' => ['nullable', 'date'],
            'official_fee' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($data['status'])) {
            $newStatus = RenewalStatus::from($data['status']);

            if ($newStatus === RenewalStatus::Instructed && $renewal->status !== RenewalStatus::Instructed) {
                $data['instructed_at'] = now();
            }

            if ($newStatus === RenewalStatus::Paid && $renewal->status !== RenewalStatus::Paid) {
                $data['paid_at'] = now();
            }
        }

        $renewal->update($data);

        return back()->with('success', 'Renewal updated.');
    }

    public function destroy(Renewal $renewal): RedirectResponse
    {
        $renewal->delete();

        return back()->with('success', 'Renewal deleted.');
    }
}
