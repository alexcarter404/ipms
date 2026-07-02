<?php

namespace App\Http\Controllers;

use App\Enums\MatterStatus;
use App\Enums\MatterType;
use App\Enums\PartyRole;
use App\Enums\TaskPriority;
use App\Enums\TriggerEvent;
use App\Models\Client;
use App\Models\CommTemplate;
use App\Models\Family;
use App\Models\Matter;
use App\Models\Party;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    public function index(Request $request): Response
    {
        $matters = Matter::query()
            ->with(['client:id,name', 'responsibleUser:id,name'])
            ->search($request->input('search'))
            ->when($request->input('type'), fn ($q, $type) => $q->where('matter_type', $type))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('country'), fn ($q, $country) => $q->where('country_code', $country))
            ->when($request->input('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->when(
                $request->input('sort') === 'reference',
                fn ($q) => $q->orderBy('reference'),
                fn ($q) => $q->latest()
            )
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Matters/Index', [
            'matters' => $matters,
            'filters' => $request->only('search', 'type', 'status', 'country', 'client_id', 'sort'),
            'types' => MatterType::options(),
            'statuses' => MatterStatus::options(),
            'countries' => Countries::options(),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Matters/Create', [
            'options' => $this->formOptions(),
            'preselectedClientId' => $request->integer('client_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $matter = Matter::create($this->validated($request));

        return redirect()->route('matters.show', $matter)
            ->with('success', 'Matter created.');
    }

    public function show(Matter $matter): Response
    {
        $matter->load([
            'client:id,name,code',
            'contact:id,name,email',
            'family:id,reference,name',
            'parent:id,reference,title',
            'children:id,parent_id,reference,title,country_code,status',
            'responsibleUser:id,name',
            'parties',
            'classes',
            'renewals',
            'tasks.assignee:id,name',
            'communications.creator:id,name',
            'communications.template:id,name',
        ]);

        return Inertia::render('Matters/Show', [
            'matter' => $matter,
            'countryName' => Countries::name($matter->country_code),
            'parties' => Party::orderBy('name')->get(['id', 'name']),
            'partyRoles' => PartyRole::options(),
            'workflows' => Workflow::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
                ->with('steps:id,workflow_id,title,offset_value,offset_unit')
                ->get(),
            'triggerEvents' => TriggerEvent::options(),
            'templates' => CommTemplate::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('matter_type')->orWhere('matter_type', $matter->matter_type))
                ->get(['id', 'name', 'channel']),
            'users' => User::orderBy('name')->get(['id', 'name']),
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

    public function update(Request $request, Matter $matter): RedirectResponse
    {
        $matter->update($this->validated($request, $matter));

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
            'clients' => Client::orderBy('name')->get(['id', 'name', 'code']),
            'families' => Family::orderBy('reference')->get(['id', 'reference', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'matters' => Matter::orderBy('reference')->get(['id', 'reference', 'title']),
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

    private function validated(Request $request, ?Matter $matter = null): array
    {
        return $request->validate([
            'reference' => ['required', 'string', 'max:30', Rule::unique('matters')->ignore($matter)->whereNull('deleted_at')],
            'matter_type' => ['required', Rule::enum(MatterType::class)],
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'exists:clients,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'family_id' => ['nullable', 'exists:families,id'],
            'parent_id' => ['nullable', 'exists:matters,id', Rule::notIn([$matter?->id])],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'country_code' => ['required', 'string', 'size:2'],
            'filing_route' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(MatterStatus::class)],
            'application_no' => ['nullable', 'string', 'max:50'],
            'application_date' => ['nullable', 'date'],
            'publication_no' => ['nullable', 'string', 'max:50'],
            'publication_date' => ['nullable', 'date'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'registration_date' => ['nullable', 'date'],
            'priority_no' => ['nullable', 'string', 'max:50'],
            'priority_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
