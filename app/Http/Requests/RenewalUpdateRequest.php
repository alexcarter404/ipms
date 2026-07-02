<?php

namespace App\Http\Requests;

use App\Enums\RenewalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewalUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(RenewalStatus::class)],
            'due_date' => ['sometimes', 'date'],
            'grace_date' => ['nullable', 'date'],
            'official_fee' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
