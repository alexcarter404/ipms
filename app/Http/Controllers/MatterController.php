<?php

namespace App\Http\Controllers;

use App\Actions\Matters\SaveMatter;
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
use App\Repositories\FamilyRepository;
use App\Repositories\MatterRepository;
use App\Repositories\PartyRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkflowRepository;
use App\Services\RenewalScheduler;
use App\Support\Countries;
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

    public function create(Request $request): Response
    {
        return Inertia::render('Matters/Create', [
            'options' => $this->formOptions(),
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
        ]);
    }

    public function edit(Matter $matter): Response
    {
        return Inertia::render('Matters/Edit', [
            'matter' => $matter,
            'options' => $this->formOptions(),
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

    private function formOptions(): array
    {
        return [
            'types' => MatterType::options(),
            'statuses' => MatterStatus::options(),
            'countries' => Countries::options(),
            'clients' => app(ClientRepository::class)->optionsWithEntities(),
            'families' => app(FamilyRepository::class)->options(),
            'users' => app(UserRepository::class)->options(),
            'matters' => $this->matters->referenceOptions(),
            'filingRoutes' => [
                ['value' => 'national', 'label' => 'National'],
                ['value' => 'pct', 'label' => 'PCT'],
                ['value' => 'ep', 'label' => 'European Patent (EP)'],
                ['value' => 'madrid', 'label' => 'Madrid Protocol'],
                ['value' => 'hague', 'label' => 'Hague System'],
                ['value' => 'paris', 'label' => 'Paris Convention'],
            ],
        ];
    }
}
