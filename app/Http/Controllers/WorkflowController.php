<?php

namespace App\Http\Controllers;

use App\Enums\MatterType;
use App\Enums\TriggerEvent;
use App\Models\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Workflows/Index', [
            'workflows' => Workflow::withCount('steps')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Workflows/Create', [
            'types' => MatterType::options(),
            'triggerEvents' => TriggerEvent::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $workflow = DB::transaction(function () use ($data) {
            $workflow = Workflow::create($data);
            $this->syncSteps($workflow, $data['steps']);

            return $workflow;
        });

        return redirect()->route('workflows.edit', $workflow)
            ->with('success', 'Workflow created.');
    }

    public function edit(Workflow $workflow): Response
    {
        $workflow->load('steps');

        return Inertia::render('Workflows/Edit', [
            'workflow' => $workflow,
            'types' => MatterType::options(),
            'triggerEvents' => TriggerEvent::options(),
        ]);
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($workflow, $data) {
            $workflow->update($data);
            $this->syncSteps($workflow, $data['steps']);
        });

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow updated.');
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $workflow->delete();

        return redirect()->route('workflows.index')->with('success', 'Workflow deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'matter_type' => ['nullable', Rule::enum(MatterType::class)],
            'trigger_event' => ['required', Rule::enum(TriggerEvent::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'steps' => ['array'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string'],
            'steps.*.offset_value' => ['required', 'integer', 'between:-3650,36500'],
            'steps.*.offset_unit' => ['required', Rule::in(['days', 'weeks', 'months', 'years'])],
            'steps.*.is_critical' => ['boolean'],
        ]);
    }

    private function syncSteps(Workflow $workflow, array $steps): void
    {
        $keptIds = [];

        foreach (array_values($steps) as $i => $step) {
            $attributes = [
                'title' => $step['title'],
                'description' => $step['description'] ?? null,
                'offset_value' => $step['offset_value'],
                'offset_unit' => $step['offset_unit'],
                'is_critical' => $step['is_critical'] ?? false,
                'sort_order' => $i,
            ];

            if (! empty($step['id']) && $workflow->steps()->whereKey($step['id'])->exists()) {
                $workflow->steps()->whereKey($step['id'])->first()->update($attributes);
                $keptIds[] = $step['id'];
            } else {
                $keptIds[] = $workflow->steps()->create($attributes)->id;
            }
        }

        $workflow->steps()->whereNotIn('id', $keptIds)->delete();
    }
}
