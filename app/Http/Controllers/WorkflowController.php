<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\SaveWorkflow;
use App\Enums\MatterType;
use App\Enums\TriggerEvent;
use App\Http\Requests\WorkflowRequest;
use App\Enums\OfficeEventType;
use App\Support\ContractFields;
use App\Models\Workflow;
use App\Repositories\WorkflowRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(WorkflowRepository $workflows): Response
    {
        return Inertia::render('Workflows/Index', [
            'workflows' => $workflows->allWithStepCounts(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Workflows/Create', [
            'types' => MatterType::options(),
            'triggerEvents' => TriggerEvent::options(),
            'contractFields' => ContractFields::options(),
            'officeEvents' => OfficeEventType::options(),
        ]);
    }

    public function store(WorkflowRequest $request, SaveWorkflow $action): RedirectResponse
    {
        $workflow = $action->create($request->validated());

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
            'contractFields' => ContractFields::options(),
            'officeEvents' => OfficeEventType::options(),
        ]);
    }

    public function update(WorkflowRequest $request, Workflow $workflow, SaveWorkflow $action): RedirectResponse
    {
        $action->update($workflow, $request->validated());

        return redirect()->route('workflows.index')
            ->with('success', 'Workflow updated.');
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $workflow->delete();

        return redirect()->route('workflows.index')->with('success', 'Workflow deleted.');
    }
}
