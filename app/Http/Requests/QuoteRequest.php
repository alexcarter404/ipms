<?php

namespace App\Http\Requests;

use App\Support\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'client_entity_id' => [
                'nullable',
                Rule::exists('client_entities', 'id')->where('client_id', $this->input('client_id')),
            ],
            'matter_id' => ['nullable', 'exists:matters,id'],
            'currency_code' => ['required', Rule::in(Currencies::codes())],
            'valid_until' => ['nullable', 'date'],
            'tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_amount' => ['required', 'numeric'],
        ];
    }
}
