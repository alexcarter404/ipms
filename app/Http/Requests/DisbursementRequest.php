<?php

namespace App\Http\Requests;

use App\Support\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisbursementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'cost_amount' => ['required', 'numeric', 'min:0'],
            'cost_currency' => ['required', Rule::in(Currencies::codes())],
            'markup_pct' => ['nullable', 'numeric', 'between:0,1000'],
        ];
    }
}
