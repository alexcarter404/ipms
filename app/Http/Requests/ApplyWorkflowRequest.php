<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyWorkflowRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workflow_id' => ['required', 'exists:workflows,id'],
            'base_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
