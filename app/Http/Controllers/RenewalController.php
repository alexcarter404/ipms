<?php

namespace App\Http\Controllers;

use App\Actions\Renewals\CreateRenewal;
use App\Actions\Renewals\GenerateRenewalSchedule;
use App\Actions\Renewals\UpdateRenewal;
use App\Enums\RenewalStatus;
use App\Exceptions\DomainActionException;
use App\Http\Requests\RenewalStoreRequest;
use App\Http\Requests\RenewalUpdateRequest;
use App\Models\Matter;
use App\Models\Renewal;
use App\Repositories\RenewalRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RenewalController extends Controller
{
    public function index(Request $request, RenewalRepository $renewals): Response
    {
        $filters = $request->only('status', 'due_within', 'search');

        return Inertia::render('Renewals/Index', [
            'renewals' => $renewals->paginateFiltered($filters),
            'filters' => $filters,
            'statuses' => RenewalStatus::options(),
        ]);
    }

    public function generate(Matter $matter, GenerateRenewalSchedule $action): RedirectResponse
    {
        try {
            $created = $action->handle($matter);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$created->count()} renewal(s) generated using “{$action->ruleName($matter)}”.");
    }

    public function store(RenewalStoreRequest $request, Matter $matter, CreateRenewal $action): RedirectResponse
    {
        $action->handle($matter, $request->validated());

        return back()->with('success', 'Renewal added.');
    }

    public function update(RenewalUpdateRequest $request, Renewal $renewal, UpdateRenewal $action): RedirectResponse
    {
        $action->handle($renewal, $request->validated());

        return back()->with('success', 'Renewal updated.');
    }

    public function destroy(Renewal $renewal): RedirectResponse
    {
        $renewal->delete();

        return back()->with('success', 'Renewal deleted.');
    }
}
