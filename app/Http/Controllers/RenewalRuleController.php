<?php

namespace App\Http\Controllers;

use App\Enums\MatterType;
use App\Models\RenewalRule;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RenewalRuleController extends Controller
{
    public function index(): Response
    {
        $rules = RenewalRule::query()
            ->orderBy('matter_type')
            ->orderByRaw('country_code is not null') // type-wide defaults first
            ->orderBy('country_code')
            ->get()
            ->map(fn (RenewalRule $rule) => [
                ...$rule->toArray(),
                'summary' => $rule->summary(),
                'country_name' => Countries::name($rule->country_code),
            ]);

        return Inertia::render('RenewalRules/Index', [
            'rules' => $rules,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('RenewalRules/Create', [
            'types' => MatterType::options(),
            'countries' => Countries::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RenewalRule::create($this->validated($request));

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

    public function update(Request $request, RenewalRule $renewalRule): RedirectResponse
    {
        $renewalRule->update($this->validated($request, $renewalRule));

        return redirect()->route('renewal-rules.index')->with('success', 'Renewal rule updated.');
    }

    public function destroy(RenewalRule $renewalRule): RedirectResponse
    {
        $renewalRule->delete();

        return redirect()->route('renewal-rules.index')->with('success', 'Renewal rule deleted.');
    }

    private function validated(Request $request, ?RenewalRule $rule = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'matter_type' => ['required', Rule::enum(MatterType::class)],
            'country_code' => [
                'nullable', 'string', 'size:2',
                Rule::unique('renewal_rules')
                    ->where('matter_type', $request->input('matter_type'))
                    ->ignore($rule),
            ],
            'base_date' => ['required', Rule::in(['application', 'registration'])],
            'schedule_mode' => ['required', Rule::in(['regular', 'fixed'])],
            'start_cycle' => ['required_if:schedule_mode,regular', 'nullable', 'integer', 'between:1,100'],
            'end_cycle' => ['required_if:schedule_mode,regular', 'nullable', 'integer', 'between:1,100', 'gte:start_cycle'],
            'interval_years' => ['required_if:schedule_mode,regular', 'nullable', 'integer', 'between:1,50'],
            'offsets_months' => ['array', 'exclude_unless:schedule_mode,fixed'],
            'offsets_months.*' => ['integer', 'between:1,1200'],
            'grace_months' => ['required', 'integer', 'between:0,24'],
            'default_official_fee' => ['nullable', 'numeric', 'min:0'],
            'default_service_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['schedule_mode'] === 'fixed') {
            $data['offsets_months'] = array_values($data['offsets_months'] ?? []);
            $data['start_cycle'] = null;
            $data['end_cycle'] = null;
            $data['interval_years'] = null;
        } else {
            $data['offsets_months'] = null;
        }

        unset($data['schedule_mode']);

        if (! empty($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        return $data;
    }
}
