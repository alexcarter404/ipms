<?php

namespace App\Http\Requests;

use App\Enums\MatterStatus;
use App\Enums\MatterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatterRequest extends FormRequest
{
    public function rules(): array
    {
        $matter = $this->route('matter'); // null on store

        return [
            'reference' => ['required', 'string', 'max:30', Rule::unique('matters')->ignore($matter)->whereNull('deleted_at')],
            'matter_type' => ['required', Rule::enum(MatterType::class)],
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'exists:clients,id'],
            'client_entity_id' => [
                'nullable',
                Rule::exists('client_entities', 'id')->where('client_id', $this->input('client_id')),
            ],
            'family_id' => ['nullable', 'exists:families,id'],
            'parent_id' => ['nullable', 'exists:matters,id', Rule::notIn([$matter?->id])],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'country_code' => ['required', 'string', 'size:2'],
            'filing_route' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(MatterStatus::class)],
            'application_no' => ['nullable', 'string', 'max:50'],
            'application_date' => ['nullable', 'date'],
            'publication_no' => ['nullable', 'string', 'max:50'],
            'publication_date' => ['nullable', 'date'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'registration_date' => ['nullable', 'date'],
            'priority_no' => ['nullable', 'string', 'max:50'],
            'priority_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
