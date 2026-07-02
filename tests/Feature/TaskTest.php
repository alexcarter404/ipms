<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_task_can_be_created_on_matter(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)->post(route('matters.tasks.store', $matter), [
            'title' => 'File response to office action',
            'due_date' => now()->addMonths(3)->toDateString(),
            'priority' => 'high',
            'is_critical' => true,
        ]);

        $this->assertDatabaseHas('matter_tasks', [
            'matter_id' => $matter->id,
            'title' => 'File response to office action',
            'is_critical' => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_internal_date_must_not_be_after_due_date(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)
            ->post(route('matters.tasks.store', $matter), [
                'title' => 'Task',
                'due_date' => now()->addDays(10)->toDateString(),
                'internal_date' => now()->addDays(20)->toDateString(),
                'priority' => 'normal',
            ])
            ->assertSessionHasErrors('internal_date');
    }

    public function test_completing_a_task_records_who_and_when(): void
    {
        $task = MatterTask::factory()->create();

        $this->actingAs($this->user)->patch(route('tasks.update', $task), [
            'status' => 'completed',
        ]);

        $task->refresh();
        $this->assertSame('completed', $task->status->value);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($this->user->id, $task->completed_by);
    }

    public function test_tasks_index_shows_open_tasks_by_default(): void
    {
        MatterTask::factory()->count(2)->create();
        MatterTask::factory()->create(['status' => 'completed']);

        $this->actingAs($this->user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tasks/Index')
                ->has('tasks.data', 2));
    }

    public function test_tasks_index_filters_overdue_and_assignee(): void
    {
        MatterTask::factory()->overdue()->create(['assigned_to' => $this->user->id]);
        MatterTask::factory()->create(); // future, unassigned

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['overdue' => 1]))
            ->assertInertia(fn ($page) => $page->has('tasks.data', 1));

        $this->actingAs($this->user)
            ->get(route('tasks.index', ['assignee' => 'me']))
            ->assertInertia(fn ($page) => $page->has('tasks.data', 1));
    }

    public function test_task_can_be_deleted(): void
    {
        $task = MatterTask::factory()->create();

        $this->actingAs($this->user)->delete(route('tasks.destroy', $task));

        $this->assertDatabaseMissing('matter_tasks', ['id' => $task->id]);
    }
}
