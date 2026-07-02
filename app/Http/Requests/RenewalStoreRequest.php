<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewalStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cycle' => [
                'required', 'integer', 'min:1',
                Rule::unique('renewals')->where('matter_id', $this->route('matter')->id),
            ],
            'due_date' => ['required', 'date'],
            'grace_date' => ['nullable', 'date', 'after_or_equal:due_date'],
            'official_fee' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
