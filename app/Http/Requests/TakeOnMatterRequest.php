<?php

namespace App\Http\Requests;

use App\Models\Workflow;
use Illuminate\Validation\Rule;

/**
 * Matter take-on: the base matter rules plus the entry stage's data
 * contract — every field the chosen stage (and all earlier stages)
 * demands becomes required, as does the trigger's base date.
 */
class TakeOnMatterRequest extends MatterRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['workflow_id'] = ['required', 'exists:workflows,id'];
        $rules['entry_step_id'] = [
            'required',
            Rule::exists('workflow_steps', 'id')->where('workflow_id', $this->input('workflow_id')),
        ];
        $rules['base_date'] = ['nullable', 'date'];

        $workflow = Workflow::with('steps')->find($this->input('workflow_id'));
        $entryStep = $workflow?->steps->firstWhere('id', (int) $this->input('entry_step_id'));

        if ($workflow && $entryStep) {
            foreach ($workflow->contractUpTo($entryStep) as $field) {
                $rules[$field] = $this->makeRequired($rules[$field] ?? []);
            }

            // The remaining deadlines are anchored on the trigger date:
            // a matter date when the trigger implies one, otherwise an
            // explicit base date.
            if ($dateField = $workflow->trigger_event->dateField()) {
                $rules[$dateField] = $this->makeRequired($rules[$dateField] ?? ['date']);
            } else {
                $rules['base_date'] = ['required', 'date'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'The :attribute is required to take the matter on at this stage.',
        ];
    }

    private function makeRequired(array $rules): array
    {
        return array_values(array_merge(
            ['required'],
            array_filter($rules, fn ($rule) => $rule !== 'nullable' && $rule !== 'required')
        ));
    }
}
