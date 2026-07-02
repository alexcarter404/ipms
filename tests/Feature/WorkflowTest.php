<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_workflow_with_steps_can_be_created(): void
    {
        $this->actingAs($this->user)->post(route('workflows.store'), [
            'name' => 'Office Action Response',
            'matter_type' => null,
            'trigger_event' => 'office_action',
            'is_active' => true,
            'steps' => [
                ['title' => 'Report to client', 'offset_value' => 5, 'offset_unit' => 'days', 'is_critical' => false],
                ['title' => 'File response', 'offset_value' => 3, 'offset_unit' => 'months', 'is_critical' => true],
            ],
        ]);

        $workflow = Workflow::firstWhere('name', 'Office Action Response');
        $this->assertNotNull($workflow);
        $this->assertSame(2, $workflow->steps()->count());
        $this->assertSame([0, 1], $workflow->steps->pluck('sort_order')->all());
    }

    public function test_updating_workflow_syncs_steps(): void
    {
        $workflow = Workflow::factory()->create();
        $keep = WorkflowStep::factory()->create(['workflow_id' => $workflow->id, 'title' => 'Keep me']);
        $drop = WorkflowStep::factory()->create(['workflow_id' => $workflow->id, 'title' => 'Drop me']);

        $this->actingAs($this->user)->patch(route('workflows.update', $workflow), [
            'name' => $workflow->name,
            'trigger_event' => $workflow->trigger_event->value,
            'is_active' => true,
            'steps' => [
                ['id' => $keep->id, 'title' => 'Kept & renamed', 'offset_value' => 1, 'offset_unit' => 'weeks', 'is_critical' => false],
                ['id' => null, 'title' => 'Brand new step', 'offset_value' => 2, 'offset_unit' => 'months', 'is_critical' => true],
            ],
        ]);

        $titles = $workflow->fresh()->steps->pluck('title')->all();
        $this->assertSame(['Kept & renamed', 'Brand new step'], $titles);
        $this->assertDatabaseMissing('workflow_steps', ['id' => $drop->id]);
    }

    public function test_applying_workflow_creates_tasks_with_offset_due_dates(): void
    {
        $matter = Matter::factory()->create([
            'application_date' => '2026-01-15',
            'responsible_user_id' => $this->user->id,
        ]);

        $workflow = Workflow::factory()->create(['trigger_event' => 'filing']);
        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'title' => 'Report filing',
            'offset_value' => 7,
            'offset_unit' => 'days',
        ]);
        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'title' => 'Request examination',
            'offset_value' => 12,
            'offset_unit' => 'months',
            'is_critical' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('matters.workflows.apply', $matter), ['workflow_id' => $workflow->id])
            ->assertSessionHas('success');

        $this->assertSame(2, $matter->tasks()->count());

        $report = $matter->tasks()->firstWhere('title', 'Report filing');
        $this->assertSame('2026-01-22', $report->due_date->toDateString());
        $this->assertSame($this->user->id, $report->assigned_to);

        $exam = $matter->tasks()->firstWhere('title', 'Request examination');
        $this->assertSame('2027-01-15', $exam->due_date->toDateString());
        $this->assertTrue($exam->is_critical);
        $this->assertSame('critical', $exam->priority->value);
    }

    public function test_manual_base_date_overrides_matter_date(): void
    {
        $matter = Matter::factory()->create(['application_date' => '2026-01-15']);
        $workflow = Workflow::factory()->create(['trigger_event' => 'filing']);
        WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'offset_value' => 1,
            'offset_unit' => 'months',
        ]);

        $this->actingAs($this->user)->post(route('matters.workflows.apply', $matter), [
            'workflow_id' => $workflow->id,
            'base_date' => '2026-06-01',
        ]);

        $this->assertSame('2026-07-01', $matter->tasks()->first()->due_date->toDateString());
    }

    public function test_applying_workflow_without_base_date_fails_gracefully(): void
    {
        $matter = Matter::factory()->create(['application_date' => null]);
        $workflow = Workflow::factory()->create(['trigger_event' => 'filing']);
        WorkflowStep::factory()->create(['workflow_id' => $workflow->id]);

        $this->actingAs($this->user)
            ->from(route('matters.show', $matter))
            ->post(route('matters.workflows.apply', $matter), ['workflow_id' => $workflow->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $matter->tasks()->count());
    }

    public function test_workflow_can_be_deleted(): void
    {
        $workflow = Workflow::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('workflows.destroy', $workflow))
            ->assertRedirect(route('workflows.index'));

        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    }
}
