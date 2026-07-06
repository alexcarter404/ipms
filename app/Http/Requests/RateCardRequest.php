<?php

namespace App\Http\Requests;

use App\Enums\MatterType;
use App\Enums\TimekeeperRole;
use App\Support\Currencies;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RateCardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'role' => ['nullable', Rule::enum(TimekeeperRole::class)],
            'client_id' => ['nullable', 'exists:clients,id'],
            'matter_type' => ['nullable', Rule::enum(MatterType::class)],
            'activity_code_id' => ['nullable', 'exists:activity_codes,id'],
            'currency_code' => ['required', Rule::in(Currencies::codes())],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
