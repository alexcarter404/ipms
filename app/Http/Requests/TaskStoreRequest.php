<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'internal_date' => ['nullable', 'date', 'before_or_equal:due_date'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'is_critical' => ['boolean'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
