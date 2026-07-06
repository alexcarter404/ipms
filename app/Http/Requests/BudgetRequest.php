<?php

namespace App\Http\Requests;

use App\Support\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_code' => ['nullable', Rule::in(Currencies::codes())],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
