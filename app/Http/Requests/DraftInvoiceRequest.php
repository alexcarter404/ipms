<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DraftInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'include_time' => ['boolean'],
            'include_disbursements' => ['boolean'],
            'include_charges' => ['boolean'],
            'through' => ['nullable', 'date'],
        ];
    }
}
