<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TemplatePreviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'exists:comm_templates,id'],
            'matter_id' => ['required', 'exists:matters,id'],
        ];
    }
}
