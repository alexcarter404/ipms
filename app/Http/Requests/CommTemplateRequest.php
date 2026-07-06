<?php

namespace App\Http\Requests;

use App\Enums\MatterType;
use App\Enums\OfficeEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'letter'])],
            'matter_type' => ['nullable', Rule::enum(MatterType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
            'auto_event' => ['nullable', Rule::enum(OfficeEventType::class)],
        ];
    }
}
