<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('activity_codes', 'code')->ignore($this->route('activityCode')),
            ],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
