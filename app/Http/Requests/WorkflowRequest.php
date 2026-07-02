<?php

namespace App\Http\Requests;

use App\Enums\MatterType;
use App\Enums\TriggerEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowRequest extends FormRequest
{
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
        ];
    }
}
