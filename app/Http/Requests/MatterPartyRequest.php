<?php

namespace App\Http\Requests;

use App\Enums\PartyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatterPartyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'party_id' => ['nullable', 'exists:parties,id', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:255', 'required_without:party_id'],
            'party_type' => ['nullable', Rule::in(['individual', 'organisation'])],
            'role' => ['required', Rule::enum(PartyRole::class)],
        ];
    }
}
