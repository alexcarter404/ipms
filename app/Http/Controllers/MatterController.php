<?php

namespace App\Http\Controllers;

use App\Actions\Matters\SaveMatter;
use App\Enums\AgreementType;
use App\Enums\ChargeType;
use App\Enums\ContactType;
use App\Enums\MatterContactRole;
use App\Enums\MatterStatus;
use App\Enums\MatterType;
use App\Enums\PartyRole;
use App\Enums\TaskPriority;
use App\Enums\TriggerEvent;
use App\Http\Requests\MatterRequest;
use App\Models\Matter;
use App\Repositories\ClientRepository;
use App\Repositories\CommTemplateRepository;
use App\Repositories\ContactRepository;
use App\Repositories\MatterRepository;
use App\Repositories\PartyRepository;
use App\Repositories\BillingSettingsRepository;
use App\Repositories\UserRepository;
use App\Repositories\WipRepository;
use App\Repositories\WorkflowRepository;
use App\Services\MatterFormOptions;
use App\Services\RenewalScheduler;
use App\Support\Countries;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    public function __construct(private MatterRepository $matters)
    {
    }

    public function index(Request $request, ClientRepository $clients): Response
    {
        $filters = $request->only('search', 'type', 'status', 'country', 'client_id', 'sort');

        return Inertia::render('Matters/Index', [
            'matters' => $this->matters->paginateFiltered($filters),
            'filters' => $filters,
            'types' => MatterType::options(),
            'statuses' => MatterStatus::options(),
            'countries' => Countries::options(),
            'clients' => $clients->options(),
        ]);
    }

    public function create(Request $request, MatterFormOptions $options): Response
    {
        return Inertia::render('Matters/Create', [
            'options' => $options->build(),
            'preselectedClientId' => $request->integer('client_id') ?: null,
        ]);
    }

    public function store(MatterRequest $request, SaveMatter $action): RedirectResponse
    {
        $matter = $action->create($request->validated());

        return redirect()->route('matters.show', $matter)
            ->with('success', 'Matter created.');
    }

    public function show(
        Matter $matter,
        RenewalScheduler $scheduler,
        PartyRepository $parties,
        ContactRepository $contacts,
        WorkflowRepository $workflows,
        CommTemplateRepository $templates,
        UserRepository $users,
        WipRepository $wip,
        BillingSettingsRepository $billingSettings,
    ): Response {
        $this->matters->loadForDisplay($matter);

        $renewalRule = $scheduler->ruleFor($matter);
        $billingEntity = $matter->effectiveBillingEntity();

        return Inertia::render('Matters/Show', [
            'matter' => $matter,
            'countryName' => Countries::name($matter->country_code),
            'billingEntity' => $billingEntity ? [
                'id' => $billingEntity->id,
                'name' => $billingEntity->name,
                'billing_email' => $billingEntity->billing_email,
                'is_fallback' => $matter->client_entity_id === null,
            ] : null,
            'renewalRule' => $renewalRule ? [
                'id' => $renewalRule->id,
                'name' => $renewalRule->name,
                'summary' => $renewalRule->summary(),
            ] : null,
            'parties' => $parties->options(),
            'partyRoles' => PartyRole::options(),
            'clientContacts' => $contacts->forClient($matter->client),
            'contactRoles' => MatterContactRole::options(),
            'contactTypes' => ContactType::options(),
            'workflows' => $workflows->activeForMatter($matter),
            'triggerEvents' => TriggerEvent::options(),
            'templates' => $templates->activeForMatter($matter),
            'users' => $users->options(),
            'priorities' => TaskPriority::options(),
            'baseDates' => [
                'filing' => $matter->application_date?->toDateString(),
                'publication' => $matter->publication_date?->toDateString(),
                'grant' => $matter->registration_date?->toDateString(),
                'registration' => $matter->registration_date?->toDateString(),
                'priority' => $matter->priority_date?->toDateString(),
            ],
            'billingAgreement' => $matter->billingAgreement?->load('stages.charge'),
            'billing' => array_merge($wip->forMatter($matter), [
                'wip' => $wip->totals($matter),
                'currency' => $matter->billingCurrency(),
            ]),
            'billingOptions' => [
                'agreementTypes' => AgreementType::options(),
                'chargeTypes' => ChargeType::options(),
                'activityCodes' => $billingSettings->activityCodeOptions(),
                'currencies' => Currencies::options(),
            ],
        ]);
    }

    public function edit(Matter $matter, MatterFormOptions $options): Response
    {
        return Inertia::render('Matters/Edit', [
            'matter' => $matter,
            'options' => $options->build(),
        ]);
    }

    public function update(MatterRequest $request, Matter $matter, SaveMatter $action): RedirectResponse
    {
        $action->update($matter, $request->validated());

        return redirect()->route('matters.show', $matter)
            ->with('success', 'Matter updated.');
    }

    public function destroy(Matter $matter): RedirectResponse
    {
        $matter->delete();

        return redirect()->route('matters.index')->with('success', 'Matter deleted.');
    }

}
