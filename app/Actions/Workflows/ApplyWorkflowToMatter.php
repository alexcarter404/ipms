<?php

namespace App\Actions\Workflows;

use App\Exceptions\DomainActionException;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowRunner;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ApplyWorkflowToMatter
{
    public function __construct(private WorkflowRunner $runner) {}

    /**
     * Fan a workflow's steps out into matter tasks, anchored on the
     * given base date or the matter date implied by the trigger event.
     *
     * @return Collection<int, MatterTask>
     */
    public function handle(Matter $matter, Workflow $workflow, ?string $baseDate, ?User $actor, ?int $assigneeId): Collection
    {
        $workflow->loadMissing('steps');

        $resolvedBase = $baseDate
            ? Carbon::parse($baseDate)
            : $workflow->trigger_event->baseDate($matter);

        if (! $resolvedBase) {
            throw new DomainActionException(
                "The matter has no {$workflow->trigger_event->label()} — enter a base date to apply this workflow."
            );
        }

        if ($workflow->steps->isEmpty()) {
            throw new DomainActionException('This workflow has no steps.');
        }

        return $this->runner->apply($workflow, $matter, $resolvedBase, $actor, $assigneeId);
    }
}
