<?php

namespace App\Http\Controllers;

use App\Actions\RenewalRules\SaveRenewalRule;
use App\Enums\MatterType;
use App\Http\Requests\RenewalRuleRequest;
use App\Models\RenewalRule;
use App\Repositories\RenewalRuleRepository;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RenewalRuleController extends Controller
{
    public function index(RenewalRuleRepository $rules): Response
    {
        return Inertia::render('RenewalRules/Index', [
            'rules' => $rules->allOrdered()->map(fn (RenewalRule $rule) => [
                ...$rule->toArray(),
                'summary' => $rule->summary(),
                'country_name' => Countries::name($rule->country_code),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('RenewalRules/Create', [
            'types' => MatterType::options(),
            'countries' => Countries::options(),
        ]);
    }

    public function store(RenewalRuleRequest $request, SaveRenewalRule $action): RedirectResponse
    {
        $action->create($request->validated());

        return redirect()->route('renewal-rules.index')->with('success', 'Renewal rule created.');
    }

    public function edit(RenewalRule $renewalRule): Response
    {
        return Inertia::render('RenewalRules/Edit', [
            'rule' => $renewalRule,
            'types' => MatterType::options(),
            'countries' => Countries::options(),
        ]);
    }

    public function update(RenewalRuleRequest $request, RenewalRule $renewalRule, SaveRenewalRule $action): RedirectResponse
    {
        $action->update($renewalRule, $request->validated());

        return redirect()->route('renewal-rules.index')->with('success', 'Renewal rule updated.');
    }

    public function destroy(RenewalRule $renewalRule): RedirectResponse
    {
        $renewalRule->delete();

        return redirect()->route('renewal-rules.index')->with('success', 'Renewal rule deleted.');
    }
}
