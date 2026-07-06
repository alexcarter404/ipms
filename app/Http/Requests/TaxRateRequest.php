<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxRateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'between:0,100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'is_default' => ['boolean'],
        ];
    }
}
