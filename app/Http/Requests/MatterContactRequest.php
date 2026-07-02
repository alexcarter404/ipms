<?php

namespace App\Http\Requests;

use App\Enums\ContactType;
use App\Enums\MatterContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatterContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_id' => [
                'nullable', 'required_without:name',
                Rule::exists('contacts', 'id')->where('client_id', $this->route('matter')->client_id),
            ],
            'name' => ['nullable', 'string', 'max:255', 'required_without:contact_id'],
            'contact_type' => ['nullable', Rule::enum(ContactType::class)],
            'email' => ['nullable', 'email', 'max:255', 'required_if:contact_type,mailbox'],
            'role' => ['required', Rule::enum(MatterContactRole::class)],
        ];
    }
}
