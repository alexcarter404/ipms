<?php

namespace App\Actions\Workflows;

use App\Models\Workflow;
use Illuminate\Support\Facades\DB;

class SaveWorkflow
{
    public function create(array $data): Workflow
    {
        return DB::transaction(function () use ($data) {
            $workflow = Workflow::create($data);
            $this->syncSteps($workflow, $data['steps'] ?? []);

            return $workflow;
        });
    }

    public function update(Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow->update($data);
            $this->syncSteps($workflow, $data['steps'] ?? []);

            return $workflow;
        });
    }

    /** Upsert submitted steps in order; steps left out are removed. */
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
                'required_fields' => array_values($step['required_fields'] ?? []),
                'completed_by_event' => $step['completed_by_event'] ?? null,
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
