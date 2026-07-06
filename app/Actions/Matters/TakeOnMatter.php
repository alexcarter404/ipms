<?php

namespace App\Actions\Matters;

use App\Models\Matter;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowRunner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Open a matter part-way through a workflow (matter take-on): the entry
 * stage's data contract has already been validated, earlier steps are
 * treated as done, and tasks are generated from the entry stage onward.
 */
class TakeOnMatter
{
    public function __construct(private WorkflowRunner $runner)
    {
    }

    /**
     * @return array{matter: Matter, tasks: Collection}
     */
    public function handle(array $data, User $actor): array
    {
        $workflow = Workflow::with('steps')->findOrFail($data['workflow_id']);
        $entryStep = $workflow->steps->firstWhere('id', (int) $data['entry_step_id']);

        $attributes = collect($data)
            ->except(['workflow_id', 'entry_step_id', 'base_date'])
            ->all();

        return DB::transaction(function () use ($workflow, $entryStep, $attributes, $data, $actor) {
            $matter = Matter::create($attributes);

            $baseDate = ($dateField = $workflow->trigger_event->dateField())
                ? Carbon::parse($attributes[$dateField])
                : Carbon::parse($data['base_date']);

            $tasks = $this->runner->apply(
                $workflow, $matter, $baseDate, $actor,
                $matter->responsible_user_id, startAt: $entryStep,
            );

            return ['matter' => $matter, 'tasks' => $tasks];
        });
    }
}
