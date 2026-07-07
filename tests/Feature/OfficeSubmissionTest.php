<?php

namespace Tests\Feature;

use App\Http\Integrations\OfficeExchange\Requests\SubmitSubmissionRequest;
use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\Matter;
use App\Models\OfficeMessage;
use App\Models\OfficeSubmission;
use App\Models\User;
use App\Services\Integrations\IngestOfficeMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class OfficeSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'application_no' => 'EP21789012.3',
        ]);
    }

    private function createSubmission(array $overrides = []): OfficeSubmission
    {
        $this->actingAs($this->user)->post(route('office-submissions.store'), array_merge([
            'office' => 'epo',
            'matter_id' => $this->matter->id,
            'submission_type' => 'oa_response',
            'notes' => 'Claims amended per examiner suggestion',
        ], $overrides));

        return OfficeSubmission::latest('id')->first();
    }

    public function test_a_submission_draft_is_built_from_matter_data(): void
    {
        $task = $this->matter->tasks()->create([
            'title' => 'File response', 'due_date' => now()->addMonth(),
            'status' => 'pending', 'priority' => 'normal',
        ]);

        $submission = $this->createSubmission(['task_id' => $task->id, 'notes' => 'Claims amended']);

        $this->assertSame('draft', $submission->status->value);
        $this->assertSame($this->user->id, $submission->created_by);
        $this->assertSame('EP21789012.3', $submission->payload['application_no']);
        $this->assertSame($this->matter->client->name, $submission->payload['applicant']);
        $this->assertSame('File response', $submission->payload['responds_to']);
        $this->assertSame('Claims amended', $submission->payload['notes']);
    }

    public function test_file_drop_submission_writes_the_outbox_and_awaits_receipt(): void
    {
        Storage::fake('local');
        $submission = $this->createSubmission();

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame('submitted', $submission->status->value);
        $this->assertNotNull($submission->submitted_at);

        $files = Storage::disk('local')->files('ipo-outbox');
        $this->assertCount(1, $files);
        $package = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame($submission->id, $package['submission_id']);
        $this->assertSame('EP21789012.3', $package['application_no']);
    }

    public function test_an_inbound_receipt_acknowledges_and_completes_the_task(): void
    {
        Storage::fake('local');
        $task = $this->matter->tasks()->create([
            'title' => 'File response', 'due_date' => now()->addMonth(),
            'status' => 'pending', 'priority' => 'normal',
        ]);
        $submission = $this->createSubmission(['task_id' => $task->id]);
        $this->actingAs($this->user)->post(route('office-submissions.submit', $submission));

        // The office's receipt arrives on the inbound exchange
        app(IngestOfficeMessages::class)->ingest('epo', [[
            'external_id' => 'EPO-RCPT-1',
            'event_type' => 'receipt',
            'payload' => ['submission_id' => $submission->id, 'office_ref' => 'EP-ACK-2026-771'],
        ]]);

        $submission->refresh();
        $this->assertSame('acknowledged', $submission->status->value);
        $this->assertSame('EP-ACK-2026-771', $submission->external_ref);
        $this->assertSame('completed', $task->fresh()->status->value);

        $message = OfficeMessage::firstWhere('external_id', 'EPO-RCPT-1');
        $this->assertSame('processed', $message->status->value);
        $this->assertSame($this->matter->id, $message->matter_id);
        $this->assertContains('Completed task “File response”', $message->actions);
    }

    public function test_api_driver_submissions_acknowledge_synchronously(): void
    {
        config()->set('integrations.offices.epo', [
            'name' => 'European Patent Office', 'driver' => 'api',
            'base_url' => 'https://exchange.epo.example/v1', 'token' => 't',
        ]);
        MockClient::global([
            SubmitSubmissionRequest::class => MockResponse::make(['receipt_id' => 'EP-SYNC-99']),
        ]);

        $task = $this->matter->tasks()->create([
            'title' => 'File response', 'due_date' => now()->addMonth(),
            'status' => 'pending', 'priority' => 'normal',
        ]);
        $submission = $this->createSubmission(['task_id' => $task->id]);

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame('acknowledged', $submission->status->value);
        $this->assertSame('EP-SYNC-99', $submission->external_ref);
        $this->assertSame('completed', $task->fresh()->status->value);

        MockClient::destroyGlobal();
    }

    public function test_failed_api_submissions_record_the_error_and_can_retry(): void
    {
        config()->set('integrations.offices.epo', [
            'name' => 'European Patent Office', 'driver' => 'api',
            'base_url' => 'https://exchange.epo.example/v1', 'token' => 't',
        ]);
        MockClient::global([
            SubmitSubmissionRequest::class => MockResponse::make(['error' => 'validation'], 422),
        ]);

        $submission = $this->createSubmission();

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('error');

        $submission->refresh();
        $this->assertSame('failed', $submission->status->value);
        $this->assertNotNull($submission->error);

        // Failed submissions can be retried once the office accepts
        MockClient::destroyGlobal();
        MockClient::global([
            SubmitSubmissionRequest::class => MockResponse::make(['receipt_id' => 'EP-RETRY-1']),
        ]);
        $this->actingAs($this->user)->post(route('office-submissions.submit', $submission));
        $this->assertSame('acknowledged', $submission->fresh()->status->value);

        MockClient::destroyGlobal();
    }

    public function test_epo_prerequisites_block_submission_before_anything_is_sent(): void
    {
        Storage::fake('local');
        // An OA response with no response text — the EPO dialect refuses it
        $submission = $this->createSubmission(['notes' => null]);

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'the EPO rejects empty responses'));

        // Blocked at validation: still a draft, nothing hit the outbox
        $this->assertSame('draft', $submission->fresh()->status->value);
        $this->assertCount(0, Storage::disk('local')->files('ipo-outbox'));
    }

    public function test_an_epo_filing_lists_every_missing_prerequisite(): void
    {
        Storage::fake('local');
        // No billing entity (no address) and no responsible attorney
        $submission = $this->createSubmission(['submission_type' => 'filing']);

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'EPO Form 1001 requires the applicant\'s full address')
                && str_contains($error, 'set the responsible attorney'));

        $this->assertSame('draft', $submission->fresh()->status->value);
    }

    public function test_an_epo_filing_is_transformed_into_the_online_filing_package(): void
    {
        Storage::fake('local');
        $entity = ClientEntity::factory()->default()->create([
            'client_id' => $this->matter->client_id,
            'address' => '12 Erfinderstraße, 80331 München',
            'country_code' => 'DE',
        ]);
        $this->matter->update([
            'client_entity_id' => $entity->id,
            'responsible_user_id' => $this->user->id,
            'priority_no' => 'GB2100123.4',
            'priority_date' => '2021-03-01',
        ]);

        $submission = $this->createSubmission(['submission_type' => 'filing']);

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('success');

        $files = Storage::disk('local')->files('ipo-outbox');
        $package = json_decode(Storage::disk('local')->get($files[0]), true);

        // The EPO wire format: Form 1001 request built up from matter data
        $this->assertSame('EP1001', $package['ep_request']['form']);
        $this->assertSame($this->matter->client->name, $package['ep_request']['applicants'][0]['name']);
        $this->assertSame('12 Erfinderstraße, 80331 München', $package['ep_request']['applicants'][0]['address']);
        $this->assertSame($this->user->name, $package['ep_request']['representative']['name']);
        $this->assertSame($this->matter->title, $package['ep_request']['title_of_invention']);
        $this->assertSame('GB2100123.4', $package['ep_request']['priority_claims'][0]['number']);

        // ...plus the computed fee sheet
        $this->assertSame('001', $package['fee_sheet'][0]['code']);
        $this->assertSame(1520, $package['fee_sheet'][1]['amount']);

        // Canonical keys survive alongside the office blocks
        $this->assertSame('EP21789012.3', $package['application_no']);
        $this->assertSame($submission->id, $package['submission_id']);
    }

    public function test_offices_without_a_dialect_send_the_canonical_package_untouched(): void
    {
        Storage::fake('local');
        $submission = $this->createSubmission(['office' => 'uspto']);

        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('success');

        $files = Storage::disk('local')->files('ipo-outbox');
        $package = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertArrayNotHasKey('ep_request', $package);
        $this->assertArrayNotHasKey('fee_sheet', $package);
        $this->assertSame('EP21789012.3', $package['application_no']);
    }

    public function test_lifecycle_guards(): void
    {
        Storage::fake('local');
        $submission = $this->createSubmission();
        $this->actingAs($this->user)->post(route('office-submissions.submit', $submission));

        // Submitted packages can't be resubmitted or deleted
        $this->actingAs($this->user)
            ->post(route('office-submissions.submit', $submission))
            ->assertSessionHas('error');
        $this->actingAs($this->user)
            ->delete(route('office-submissions.destroy', $submission))
            ->assertSessionHas('error');

        $draft = $this->createSubmission();
        $this->actingAs($this->user)
            ->delete(route('office-submissions.destroy', $draft))
            ->assertSessionHas('success');
        $this->assertNull(OfficeSubmission::find($draft->id));
    }

    public function test_the_integrations_page_lists_submissions(): void
    {
        $this->createSubmission();

        $this->actingAs($this->user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('submissions', 1)
                ->where('submissions.0.status', 'draft')
                ->has('submissionTypes')
                ->has('openTasks'));
    }
}
