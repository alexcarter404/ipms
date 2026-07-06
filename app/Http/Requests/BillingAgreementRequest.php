<?php

namespace App\Http\Requests;

use App\Enums\AgreementType;
use App\Support\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingAgreementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AgreementType::class)],
            'currency_code' => ['nullable', Rule::in(Currencies::codes())],
            'increment_minutes' => ['required', 'integer', 'between:1,60'],
            'blended_rate' => ['nullable', 'numeric', 'min:0', 'required_if:type,blended'],
            'cap_amount' => ['nullable', 'numeric', 'min:0', 'required_if:type,capped'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'default_markup_pct' => ['nullable', 'numeric', 'between:0,1000'],
            'requires_task_codes' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'stages' => ['array', 'required_if:type,stage'],
            'stages.*.id' => ['nullable', 'integer'],
            'stages.*.description' => ['required', 'string', 'max:255'],
            'stages.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
