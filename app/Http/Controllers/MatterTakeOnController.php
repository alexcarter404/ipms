<?php

namespace App\Http\Controllers;

use App\Actions\Matters\TakeOnMatter;
use App\Http\Requests\TakeOnMatterRequest;
use App\Models\Workflow;
use App\Services\MatterFormOptions;
use App\Support\ContractFields;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MatterTakeOnController extends Controller
{
    public function create(MatterFormOptions $options): Response
    {
        return Inertia::render('Matters/TakeOn', [
            'options' => $options->build(),
            'workflows' => Workflow::where('is_active', true)
                ->with('steps:id,workflow_id,title,offset_value,offset_unit,required_fields,sort_order')
                ->whereHas('steps')
                ->get(['id', 'name', 'matter_type', 'trigger_event']),
            'contractFields' => ContractFields::options(),
            'triggerDateFields' => collect(\App\Enums\TriggerEvent::cases())
                ->mapWithKeys(fn ($event) => [$event->value => $event->dateField()]),
        ]);
    }

    public function store(TakeOnMatterRequest $request, TakeOnMatter $action): RedirectResponse
    {
        $result = $action->handle($request->validated(), $request->user());

        return redirect()->route('matters.show', $result['matter'])
            ->with('success', "Matter taken on — {$result['tasks']->count()} task(s) created from the entry stage onward.");
    }
}
