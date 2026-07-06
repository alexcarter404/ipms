<?php

namespace App\Http\Controllers;

use App\Actions\Billing\AddBudget;
use App\Actions\Billing\AmendBudget;
use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Matter;
use App\Repositories\BudgetRepository;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /** The portfolio budget dashboard for client/case managers. */
    public function index(
        Request $request,
        BudgetRepository $budgets,
        ClientRepository $clients,
        UserRepository $users,
    ): Response {
        // Defaults to the logged-in manager's portfolio; clearable.
        $filters = [
            'user_id' => $request->has('user_id')
                ? ($request->input('user_id') ?: null)
                : $request->user()->id,
            'client_id' => $request->input('client_id'),
        ];

        return Inertia::render('Billing/Budgets', [
            'rows' => $budgets->portfolio($filters),
            'filters' => $filters,
            'clients' => $clients->options(),
            'users' => $users->options(),
            'currencies' => Currencies::options(),
            'baseCurrency' => Currencies::base(),
        ]);
    }

    public function store(BudgetRequest $request, Matter $matter, AddBudget $action): RedirectResponse
    {
        $budget = $action->handle($matter, $request->user(), $request->validated());

        return back()->with('success', sprintf(
            'Budget added: %s %s on %s.',
            $budget->currency_code,
            number_format((float) $budget->amount, 2),
            $matter->reference
        ));
    }

    public function update(BudgetRequest $request, Budget $budget, AmendBudget $action): RedirectResponse
    {
        $action->handle($budget, $request->validated());

        return back()->with('success', 'Budget amended.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $budget->delete();

        return back()->with('success', 'Budget entry deleted.');
    }
}
