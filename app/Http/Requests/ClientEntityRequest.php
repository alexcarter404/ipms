<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientEntityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'billing_reference' => ['nullable', 'string', 'max:100'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
