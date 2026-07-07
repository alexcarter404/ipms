<?php

namespace App\Http\Requests;

use App\Enums\MatterType;
use App\Enums\OfficeEventType;
use App\Enums\TriggerEvent;
use App\Support\ContractFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Selects submit '' for "no event" — normalise to null.
        $steps = collect($this->input('steps', []))->map(function ($step) {
            if (($step['completed_by_event'] ?? null) === '') {
                $step['completed_by_event'] = null;
            }

            return $step;
        })->all();

        $this->merge(['steps' => $steps]);
    }

    public function rules(): array
    {
        return [
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
            'steps.*.required_fields' => ['nullable', 'array'],
            'steps.*.required_fields.*' => [Rule::in(ContractFields::keys())],
            'steps.*.completed_by_event' => ['nullable', Rule::enum(OfficeEventType::class)],
        ];
    }
}
