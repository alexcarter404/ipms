<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterTakeOnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** A filing-triggered workflow with per-stage data contracts. */
    private function workflow(): Workflow
    {
        $workflow = Workflow::factory()->create(['trigger_event' => 'filing']);

        $workflow->steps()->createMany([
            [
                'title' => 'Report filing',
                'offset_value' => 7, 'offset_unit' => 'days', 'sort_order' => 0,
                'required_fields' => ['application_no'],
            ],
            [
                'title' => 'File priority documents',
                'offset_value' => 3, 'offset_unit' => 'months', 'sort_order' => 1,
                'required_fields' => ['priority_no', 'priority_date'],
            ],
            [
                'title' => 'Request examination',
                'offset_value' => 12, 'offset_unit' => 'months', 'sort_order' => 2,
                'required_fields' => ['responsible_user_id'],
            ],
        ]);

        return $workflow->fresh('steps');
    }

    private function payload(Workflow $workflow, int $entryIndex, array $overrides = []): array
    {
        return array_merge([
            'workflow_id' => $workflow->id,
            'entry_step_id' => $workflow->steps[$entryIndex]->id,
            'reference' => 'TO-2026-0001',
            'matter_type' => 'patent',
            'title' => 'Transferred-in valve patent',
            'client_id' => Client::factory()->create()->id,
            'country_code' => 'GB',
            'status' => 'filed',
        ], $overrides);
    }

    public function test_step_contracts_are_saved_from_the_builder(): void
    {
        $this->actingAs($this->user)->post(route('workflows.store'), [
            'name' => 'Contracted Workflow',
            'trigger_event' => 'filing',
            'is_active' => true,
            'steps' => [
                [
                    'title' => 'Report filing',
                    'offset_value' => 7,
                    'offset_unit' => 'days',
                    'required_fields' => ['application_no', 'application_date'],
                ],
            ],
        ]);

        $workflow = Workflow::firstWhere('name', 'Contracted Workflow');
        $this->assertSame(
            ['application_no', 'application_date'],
            $workflow->steps->first()->required_fields
        );
    }

    public function test_unknown_contract_fields_are_rejected(): void
    {
        $this->actingAs($this->user)->post(route('workflows.store'), [
            'name' => 'Bad Contract',
            'trigger_event' => 'filing',
            'steps' => [
                [
                    'title' => 'Step',
                    'offset_value' => 1,
                    'offset_unit' => 'days',
                    'required_fields' => ['favourite_colour'],
                ],
            ],
        ])->assertSessionHasErrors('steps.0.required_fields.0');
    }

    public function test_contract_accumulates_across_earlier_stages(): void
    {
        $workflow = $this->workflow();

        // Entering at stage 3 demands stages 1-3's data
        $this->assertSame(
            ['application_no', 'priority_no', 'priority_date', 'responsible_user_id'],
            $workflow->contractUpTo($workflow->steps[2])
        );

        // Entering at stage 1 demands only its own
        $this->assertSame(['application_no'], $workflow->contractUpTo($workflow->steps[0]));
    }

    public function test_take_on_enforces_the_cumulative_stage_contract(): void
    {
        $workflow = $this->workflow();

        // Enter at stage 2 without its contract: application_no (stage 1),
        // priority pair (stage 2) and the trigger's filing date all missing.
        $this->actingAs($this->user)
            ->post(route('matters.take-on.store'), $this->payload($workflow, 1))
            ->assertSessionHasErrors(['application_no', 'priority_no', 'priority_date', 'application_date']);

        $this->assertDatabaseMissing('matters', ['reference' => 'TO-2026-0001']);
    }

    public function test_take_on_creates_matter_with_tasks_from_entry_stage_onward(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->user)
            ->post(route('matters.take-on.store'), $this->payload($workflow, 1, [
                'application_no' => 'GB2601234.5',
                'application_date' => '2026-01-15',
                'priority_no' => 'GB2501111.1',
                'priority_date' => '2025-01-15',
            ]))
            ->assertSessionHasNoErrors();

        $matter = Matter::firstWhere('reference', 'TO-2026-0001');
        $this->assertNotNull($matter);

        // Stage 1 is behind us — only stages 2 and 3 become tasks
        $this->assertSame(
            ['File priority documents', 'Request examination'],
            $matter->tasks()->orderBy('due_date')->pluck('title')->all()
        );

        // Due dates anchor on the trigger (filing) date
        $this->assertSame(
            ['2026-04-15', '2027-01-15'],
            $matter->tasks()->orderBy('due_date')->pluck('due_date')->map->toDateString()->all()
        );
    }

    public function test_take_on_at_the_first_stage_creates_every_task(): void
    {
        $workflow = $this->workflow();

        $this->actingAs($this->user)->post(route('matters.take-on.store'), $this->payload($workflow, 0, [
            'application_no' => 'GB2601234.5',
            'application_date' => '2026-01-15',
        ]));

        $this->assertSame(3, Matter::firstWhere('reference', 'TO-2026-0001')->tasks()->count());
    }

    public function test_manual_trigger_workflows_require_an_explicit_base_date(): void
    {
        $workflow = Workflow::factory()->create(['trigger_event' => 'office_action']);
        $step = $workflow->steps()->create([
            'title' => 'File response', 'offset_value' => 3, 'offset_unit' => 'months', 'sort_order' => 0,
        ]);

        $payload = $this->payload($workflow->fresh('steps'), 0);

        $this->actingAs($this->user)
            ->post(route('matters.take-on.store'), $payload)
            ->assertSessionHasErrors('base_date');

        $this->actingAs($this->user)
            ->post(route('matters.take-on.store'), $payload + ['base_date' => '2026-06-01'])
            ->assertSessionHasNoErrors();

        $matter = Matter::firstWhere('reference', 'TO-2026-0001');
        $this->assertSame('2026-09-01', $matter->tasks()->first()->due_date->toDateString());
    }

    public function test_entry_step_must_belong_to_the_chosen_workflow(): void
    {
        $workflow = $this->workflow();
        $other = Workflow::factory()->create();
        $foreignStep = $other->steps()->create([
            'title' => 'Foreign', 'offset_value' => 1, 'offset_unit' => 'days', 'sort_order' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('matters.take-on.store'), $this->payload($workflow, 0, [
                'entry_step_id' => $foreignStep->id,
            ]))
            ->assertSessionHasErrors('entry_step_id');
    }

    public function test_take_on_page_renders_with_workflows_and_catalogue(): void
    {
        $this->workflow();

        $this->actingAs($this->user)
            ->get(route('matters.take-on'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Matters/TakeOn')
                ->has('workflows', 1)
                ->has('workflows.0.steps', 3)
                ->has('contractFields')
                ->has('triggerDateFields')
                ->has('options.clients'));
    }
}
