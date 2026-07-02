<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $tasks = MatterTask::query()
            ->with(['matter:id,reference,title', 'assignee:id,name'])
            ->when(
                $request->input('status'),
                fn ($q, $status) => $status === 'open' ? $q->open() : $q->where('status', $status),
                fn ($q) => $q->open()
            )
            ->when($request->input('assignee') === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereDate('due_date', '<', now()))
            ->when($request->input('search'), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$term}%")
                    ->orWhereHas('matter', fn ($m) => $m->where('reference', 'like', "%{$term}%"))
            ))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $request->only('status', 'assignee', 'overdue', 'search'),
            'statuses' => TaskStatus::options(),
        ]);
    }

    public function store(Request $request, Matter $matter): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'internal_date' => ['nullable', 'date', 'before_or_equal:due_date'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'is_critical' => ['boolean'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $matter->tasks()->create($data + [
            'status' => TaskStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Task created.');
    }

    public function update(Request $request, MatterTask $task): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['sometimes', 'date'],
            'internal_date' => ['nullable', 'date'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        if (($data['status'] ?? null) === TaskStatus::Completed->value && $task->status !== TaskStatus::Completed) {
            $data['completed_at'] = now();
            $data['completed_by'] = $request->user()->id;
        }

        $task->update($data);

        return back()->with('success', 'Task updated.');
    }

    public function destroy(MatterTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
