<?php

namespace App\Http\Requests;

use App\Support\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeRateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'currency_code' => [
                'required',
                Rule::in(array_diff(Currencies::codes(), [Currencies::base()])),
            ],
            'rate' => ['required', 'numeric', 'gt:0'],
            'rate_date' => ['required', 'date'],
        ];
    }
}
