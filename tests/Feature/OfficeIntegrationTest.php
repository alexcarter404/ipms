<?php

namespace Tests\Feature;

use App\Actions\Integrations\ProcessOfficeMessage;
use App\Http\Integrations\OfficeExchange\Requests\ListMessagesRequest;
use App\Models\Client;
use App\Models\CommTemplate;
use App\Models\ExchangeRate;
use App\Models\Matter;
use App\Models\OfficeMessage;
use App\Models\User;
use App\Models\Workflow;
use App\Services\Integrations\IngestOfficeMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class OfficeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function matter(array $attributes = []): Matter
    {
        return Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'application_no' => 'EP21789012.3',
        ] + $attributes);
    }

    private function ingest(array $message, string $office = 'epo'): OfficeMessage
    {
        app(IngestOfficeMessages::class)->ingest($office, [array_merge([
            'external_id' => 'MSG-1', 'event_type' => 'grant',
        ], $message)]);

        return OfficeMessage::latest('id')->first();
    }

    public function test_ingestion_matches_by_normalised_application_number(): void
    {
        $matter = $this->matter(['application_no' => 'EP 21 789 012.3']);

        $message = $this->ingest(['application_no' => 'EP21789012.3']);

        $this->assertSame($matter->id, $message->matter_id);
        $this->assertSame('processed', $message->status->value); // auto-processed
    }

    public function test_ingestion_is_idempotent_per_office_and_external_id(): void
    {
        $this->matter();

        $this->ingest(['application_no' => 'EP21789012.3']);
        $this->ingest(['application_no' => 'EP21789012.3']);

        $this->assertSame(1, OfficeMessage::count());
    }

    public function test_unmatched_or_ambiguous_messages_wait_for_review(): void
    {
        $message = $this->ingest(['application_no' => 'EP00000000.0']);
        $this->assertSame('needs_review', $message->status->value);

        // Two matters sharing a number → ambiguous, never auto-matched
        $this->matter();
        $this->matter();
        $ambiguous = $this->ingest(['external_id' => 'MSG-2', 'application_no' => 'EP21789012.3']);
        $this->assertSame('needs_review', $ambiguous->status->value);
    }

    public function test_a_grant_updates_the_matter_and_completes_event_bound_tasks(): void
    {
        $matter = $this->matter(['status' => 'under_examination']);

        // A task whose workflow step is completed by the grant event
        $workflow = Workflow::factory()->create(['trigger_event' => 'filing']);
        $step = $workflow->steps()->create([
            'title' => 'Request examination', 'offset_value' => 12, 'offset_unit' => 'months',
            'sort_order' => 0, 'completed_by_event' => 'grant',
        ]);
        $task = $matter->tasks()->create([
            'workflow_step_id' => $step->id, 'title' => 'Request examination',
            'due_date' => now()->addMonth(), 'status' => 'pending', 'priority' => 'normal',
        ]);

        $message = $this->ingest([
            'application_no' => 'EP21789012.3',
            'registration_no' => 'EP3456789',
            'event_date' => '2026-07-01',
        ]);

        $matter->refresh();
        $this->assertSame('granted', $matter->status->value);
        $this->assertSame('EP3456789', $matter->registration_no);
        $this->assertSame('2026-07-01', $matter->registration_date->toDateString());
        $this->assertSame('completed', $task->fresh()->status->value);
        $this->assertContains('Completed task “Request examination”', $message->actions);
    }

    public function test_an_office_action_applies_the_response_workflow_adds_fees_and_drafts_the_report(): void
    {
        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.25, 'rate_date' => '2026-01-01']);
        $matter = $this->matter();

        $workflow = Workflow::factory()->create(['trigger_event' => 'office_action', 'is_active' => true]);
        $workflow->steps()->create([
            'title' => 'File response', 'offset_value' => 3, 'offset_unit' => 'months', 'sort_order' => 0,
        ]);
        CommTemplate::create([
            'name' => 'Office Action Report', 'channel' => 'email',
            'subject' => '{{matter.reference}} — OA received', 'body' => 'Dear {{contact.name}}',
            'is_active' => true, 'auto_event' => 'office_action',
        ]);

        $message = $this->ingest([
            'event_type' => 'office_action',
            'application_no' => 'EP21789012.3',
            'event_date' => '2026-06-01',
            'payload' => ['fees' => [
                ['description' => 'Extension fee', 'amount' => 125, 'currency' => 'USD'],
            ]],
        ]);

        $matter->refresh();
        $this->assertSame('office_action', $matter->status->value);

        // Response deadline chain anchored on the event date
        $task = $matter->tasks()->first();
        $this->assertSame('File response', $task->title);
        $this->assertSame('2026-09-01', $task->due_date->toDateString());

        // Official fee recorded at cost, converted to billing currency
        $disbursement = $matter->disbursements()->first();
        $this->assertSame(100.0, $disbursement->amount); // 125 USD / 1.25
        $this->assertSame('European Patent Office', $disbursement->supplier);

        // Draft comm generated for review, never sent
        $comm = $matter->communications()->first();
        $this->assertSame('draft', $comm->status);
        $this->assertStringContainsString($matter->reference, $comm->subject);

        $this->assertCount(4, $message->actions);
    }

    public function test_office_actions_recur_but_one_shot_events_do_not_reapply_workflows(): void
    {
        $matter = $this->matter();
        $workflow = Workflow::factory()->create(['trigger_event' => 'office_action', 'is_active' => true]);
        $workflow->steps()->create([
            'title' => 'File response', 'offset_value' => 3, 'offset_unit' => 'months', 'sort_order' => 0,
        ]);

        $this->ingest(['event_type' => 'office_action', 'application_no' => 'EP21789012.3', 'external_id' => 'OA-1']);
        $this->ingest(['event_type' => 'office_action', 'application_no' => 'EP21789012.3', 'external_id' => 'OA-2']);

        $this->assertSame(2, $matter->tasks()->count()); // one chain per OA
    }

    public function test_renewal_reminders_mark_the_next_renewal(): void
    {
        $matter = $this->matter();
        $renewal = $matter->renewals()->create([
            'cycle' => 2, 'due_date' => now()->addDays(60), 'status' => 'upcoming',
        ]);

        $this->ingest(['event_type' => 'renewal_reminder', 'application_no' => 'EP21789012.3']);

        $this->assertSame('reminder_sent', $renewal->fresh()->status->value);
    }

    public function test_the_file_drop_connector_polls_batches_and_archives_them(): void
    {
        Storage::fake('local');
        $this->matter();

        Storage::disk('local')->put('ipo-inbox/epo-batch1.json', json_encode([[
            'external_id' => 'EPO-777',
            'event_type' => 'publication',
            'application_no' => 'EP21789012.3',
            'event_date' => '2026-05-01',
            'payload' => ['publication_no' => 'EP4400123'],
        ]]));

        $this->artisan('ipo:poll')->assertSuccessful();

        $message = OfficeMessage::firstWhere('external_id', 'EPO-777');
        $this->assertSame('processed', $message->status->value);
        $this->assertSame('EP4400123', $message->matter->publication_no);
        // Batch archived — a second poll ingests nothing new
        $this->assertSame([], Storage::disk('local')->files('ipo-inbox'));
        $this->artisan('ipo:poll')->assertSuccessful();
        $this->assertSame(1, OfficeMessage::count());
    }

    public function test_the_saloon_api_driver_pulls_messages_from_a_rest_exchange(): void
    {
        config()->set('integrations.offices.epo', [
            'name' => 'European Patent Office',
            'driver' => 'api',
            'base_url' => 'https://exchange.epo.example/v1',
            'token' => 'test-token',
        ]);

        MockClient::global([
            ListMessagesRequest::class => MockResponse::make([
                'messages' => [[
                    'external_id' => 'EPO-API-1',
                    'event_type' => 'grant',
                    'application_no' => 'EP21789012.3',
                    'event_date' => '2026-07-01',
                ]],
            ]),
        ]);

        $matter = $this->matter(['status' => 'under_examination']);

        app(IngestOfficeMessages::class)->ingest(
            'epo',
            app(IngestOfficeMessages::class)->connector('epo')->fetch()
        );

        $this->assertSame('granted', $matter->fresh()->status->value);
        $this->assertSame('processed', OfficeMessage::firstWhere('external_id', 'EPO-API-1')->status->value);

        MockClient::destroyGlobal();
    }

    public function test_review_flow_assign_process_and_dismiss(): void
    {
        $matter = $this->matter();
        $message = $this->ingest(['application_no' => 'UNKNOWN-1']);
        $this->assertSame('needs_review', $message->status->value);

        // Processing without a matter is refused
        $this->actingAs($this->user)
            ->post(route('office-messages.process', $message))
            ->assertSessionHas('error');

        // Assign then process
        $this->actingAs($this->user)
            ->patch(route('office-messages.assign', $message), ['matter_id' => $matter->id])
            ->assertSessionHas('success');
        $this->actingAs($this->user)
            ->post(route('office-messages.process', $message))
            ->assertSessionHas('success');
        $this->assertSame('processed', $message->fresh()->status->value);

        // Processed messages are immutable
        $this->actingAs($this->user)
            ->post(route('office-messages.dismiss', $message))
            ->assertSessionHas('error');

        $other = $this->ingest(['external_id' => 'MSG-9', 'application_no' => 'UNKNOWN-2']);
        $this->actingAs($this->user)
            ->post(route('office-messages.dismiss', $other))
            ->assertSessionHas('success');
        $this->assertSame('dismissed', $other->fresh()->status->value);
    }

    public function test_the_integrations_page_renders_the_inbox(): void
    {
        $this->matter();
        $this->ingest(['application_no' => 'EP21789012.3']);

        $this->actingAs($this->user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Integrations/Index')
                ->has('messages.data', 1)
                ->has('offices')
                ->has('counts.needs_review')
                ->has('matterOptions'));
    }
}
