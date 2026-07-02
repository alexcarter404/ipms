<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Repositories\TaskRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request, TaskRepository $tasks): Response
    {
        $filters = $request->only('status', 'assignee', 'search');
        $filters['overdue'] = $request->boolean('overdue');

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks->paginateFiltered($filters, $request->user()->id),
            'filters' => $request->only('status', 'assignee', 'overdue', 'search'),
            'statuses' => TaskStatus::options(),
        ]);
    }

    public function store(TaskStoreRequest $request, Matter $matter, CreateTask $action): RedirectResponse
    {
        $action->handle($matter, $request->validated(), $request->user());

        return back()->with('success', 'Task created.');
    }

    public function update(TaskUpdateRequest $request, MatterTask $task, UpdateTask $action): RedirectResponse
    {
        $action->handle($task, $request->validated(), $request->user());

        return back()->with('success', 'Task updated.');
    }

    public function destroy(MatterTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
