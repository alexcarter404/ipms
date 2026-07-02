<?php

namespace App\Http\Requests;

use App\Enums\MatterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewalRuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'matter_type' => ['required', Rule::enum(MatterType::class)],
            'country_code' => [
                'nullable', 'string', 'size:2',
                Rule::unique('renewal_rules')
                    ->where('matter_type', $this->input('matter_type'))
                    ->ignore($this->route('renewalRule')),
            ],
            'base_date' => ['required', Rule::in(['application', 'registration'])],
            'schedule_mode' => ['required', Rule::in(['regular', 'fixed'])],
            'start_cycle' => ['required_if:schedule_mode,regular', 'nullable', 'integer', 'between:1,100'],
            'end_cycle' => ['required_if:schedule_mode,regular', 'nullable', 'integer', 'between:1,100', 'gte:start_cycle'],
            'interval_years' => ['required_if:schedule_mode,regular', 'nullable', 'integer', 'between:1,50'],
            'offsets_months' => ['array', 'exclude_unless:schedule_mode,fixed'],
            'offsets_months.*' => ['integer', 'between:1,1200'],
            'grace_months' => ['required', 'integer', 'between:0,24'],
            'default_official_fee' => ['nullable', 'numeric', 'min:0'],
            'default_service_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
