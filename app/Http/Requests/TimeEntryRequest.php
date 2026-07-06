<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeEntryRequest extends FormRequest
{
    public function rules(): array
    {
        // Task-based billing: the matter's agreement can demand an
        // activity code on every line.
        $requiresCode = (bool) $this->route('matter')
            ?->billingAgreement?->requires_task_codes;

        return [
            'user_id' => ['required', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'activity_code_id' => [
                $requiresCode ? 'required' : 'nullable',
                'exists:activity_codes,id',
            ],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'narrative' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['billable', 'non_billable'])],
        ];
    }

    public function messages(): array
    {
        return [
            'activity_code_id.required' => 'This matter\'s fee agreement requires a task code on every time entry.',
        ];
    }
}
