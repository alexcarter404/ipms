<?php

namespace App\Http\Controllers;

use App\Models\Matter;
use App\Models\Workflow;
use App\Services\WorkflowRunner;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkflowApplicationController extends Controller
{
    /** Apply a workflow template to a matter, creating its tasks. */
    public function store(Request $request, Matter $matter, WorkflowRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'workflow_id' => ['required', 'exists:workflows,id'],
            'base_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $workflow = Workflow::with('steps')->findOrFail($data['workflow_id']);

        $baseDate = isset($data['base_date'])
            ? Carbon::parse($data['base_date'])
            : $workflow->trigger_event->baseDate($matter);

        if (! $baseDate) {
            return back()->with('error', "The matter has no {$workflow->trigger_event->label()} — enter a base date to apply this workflow.");
        }

        if ($workflow->steps->isEmpty()) {
            return back()->with('error', 'This workflow has no steps.');
        }

        $created = $runner->apply($workflow, $matter, $baseDate, $request->user(), $data['assigned_to'] ?? null);

        return back()->with('success', "Workflow applied — {$created->count()} task(s) created.");
    }
}
