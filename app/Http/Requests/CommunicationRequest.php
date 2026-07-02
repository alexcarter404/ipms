<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'comm_template_id' => ['nullable', 'exists:comm_templates,id'],
            'channel' => ['required', Rule::in(['email', 'letter'])],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
