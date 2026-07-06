<?php

namespace App\Http\Controllers;

use App\Actions\Billing\DeleteBillable;
use App\Actions\Billing\LogTime;
use App\Actions\Billing\UpdateBillableStatus;
use App\Enums\BillableStatus;
use App\Exceptions\DomainActionException;
use App\Http\Requests\TimeEntryRequest;
use App\Models\Matter;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function store(TimeEntryRequest $request, Matter $matter, LogTime $action): RedirectResponse
    {
        try {
            $entry = $action->handle($matter, $request->validated());
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Time logged: %dm billed as %dm (%s %s).',
            $entry->minutes,
            $entry->billed_minutes,
            $entry->currency_code,
            number_format((float) $entry->amount, 2)
        ));
    }

    public function updateStatus(Request $request, TimeEntry $timeEntry, UpdateBillableStatus $action): RedirectResponse
    {
        return $this->transition($request, $timeEntry, $action, 'Time entry updated.');
    }

    public function destroy(TimeEntry $timeEntry, DeleteBillable $action): RedirectResponse
    {
        try {
            $action->handle($timeEntry);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Time entry deleted.');
    }

    private function transition(Request $request, TimeEntry $entry, UpdateBillableStatus $action, string $message): RedirectResponse
    {
        $request->validate(['status' => ['required', 'string']]);

        try {
            $action->handle($entry, BillableStatus::from($request->string('status')->value()));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
