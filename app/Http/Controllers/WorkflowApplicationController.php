<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\ApplyWorkflowToMatter;
use App\Exceptions\DomainActionException;
use App\Http\Requests\ApplyWorkflowRequest;
use App\Models\Matter;
use App\Models\Workflow;
use Illuminate\Http\RedirectResponse;

class WorkflowApplicationController extends Controller
{
    public function store(ApplyWorkflowRequest $request, Matter $matter, ApplyWorkflowToMatter $action): RedirectResponse
    {
        $data = $request->validated();

        try {
            $created = $action->handle(
                $matter,
                Workflow::findOrFail($data['workflow_id']),
                $data['base_date'] ?? null,
                $request->user(),
                $data['assigned_to'] ?? null,
            );
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Workflow applied — {$created->count()} task(s) created.");
    }
}
