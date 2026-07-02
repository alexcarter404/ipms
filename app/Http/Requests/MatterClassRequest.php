<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatterClassRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'class_number' => [
                'required', 'integer', 'between:1,45',
                Rule::unique('matter_classes')->where('matter_id', $this->route('matter')->id),
            ],
            'specification' => ['nullable', 'string'],
        ];
    }
}
