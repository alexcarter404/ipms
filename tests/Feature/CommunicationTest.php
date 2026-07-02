<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CommTemplate;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Matter;
use App\Models\User;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_template_renderer_replaces_merge_fields(): void
    {
        $client = Client::factory()->create(['name' => 'Acme Industries Ltd']);
        $contact = Contact::factory()->create(['client_id' => $client->id, 'name' => 'Sarah Bennett']);
        $matter = Matter::factory()->create([
            'reference' => 'P-2026-0042',
            'title' => 'Rocket skates',
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'application_date' => '2026-01-15',
        ]);

        $template = CommTemplate::factory()->create([
            'subject' => '{{matter.reference}} update',
            'body' => "Dear {{contact.name}},\nRe: {{matter.title}} filed {{matter.application_date}} for {{client.name}}.\nUnknown: {{does.not.exist}}",
        ]);

        $rendered = app(TemplateRenderer::class)->render($template, $matter);

        $this->assertSame('P-2026-0042 update', $rendered['subject']);
        $this->assertStringContainsString('Dear Sarah Bennett', $rendered['body']);
        $this->assertStringContainsString('Rocket skates filed 15 January 2026 for Acme Industries Ltd', $rendered['body']);
        // unknown fields are left intact rather than silently dropped
        $this->assertStringContainsString('{{does.not.exist}}', $rendered['body']);
    }

    public function test_preview_endpoint_returns_rendered_template(): void
    {
        $matter = Matter::factory()->create(['reference' => 'P-2026-0042']);
        $template = CommTemplate::factory()->create(['subject' => '{{matter.reference}} update']);

        $this->actingAs($this->user)
            ->postJson(route('templates.preview'), [
                'template_id' => $template->id,
                'matter_id' => $matter->id,
            ])
            ->assertOk()
            ->assertJsonPath('subject', 'P-2026-0042 update')
            ->assertJsonPath('channel', 'email');
    }

    public function test_communication_can_be_stored_as_draft(): void
    {
        $matter = Matter::factory()->create();
        $template = CommTemplate::factory()->create();

        $this->actingAs($this->user)->post(route('matters.communications.store', $matter), [
            'comm_template_id' => $template->id,
            'channel' => 'email',
            'recipient_name' => 'Sarah Bennett',
            'recipient_email' => 'sarah@example.com',
            'subject' => 'Filing confirmation',
            'body' => 'Dear Sarah, ...',
        ]);

        $this->assertDatabaseHas('communications', [
            'matter_id' => $matter->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_draft_can_be_marked_sent(): void
    {
        $comm = Communication::create([
            'matter_id' => Matter::factory()->create()->id,
            'channel' => 'email',
            'body' => 'Hello',
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)->post(route('communications.send', $comm));

        $comm->refresh();
        $this->assertSame('sent', $comm->status);
        $this->assertNotNull($comm->sent_at);
    }

    public function test_sent_communications_cannot_be_deleted(): void
    {
        $comm = Communication::create([
            'matter_id' => Matter::factory()->create()->id,
            'channel' => 'email',
            'body' => 'Hello',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($this->user)->delete(route('communications.destroy', $comm));

        $this->assertDatabaseHas('communications', ['id' => $comm->id]);
    }

    public function test_template_crud(): void
    {
        $this->actingAs($this->user)->post(route('templates.store'), [
            'name' => 'Grant Congratulations',
            'channel' => 'email',
            'subject' => 'Granted!',
            'body' => 'Dear {{contact.name}}, your patent has been granted.',
            'is_active' => true,
        ])->assertRedirect(route('templates.index'));

        $template = CommTemplate::firstWhere('name', 'Grant Congratulations');
        $this->assertNotNull($template);

        $this->actingAs($this->user)->patch(route('templates.update', $template), [
            'name' => 'Grant Letter',
            'channel' => 'letter',
            'body' => $template->body,
            'is_active' => false,
        ]);

        $this->assertSame('Grant Letter', $template->fresh()->name);

        $this->actingAs($this->user)->delete(route('templates.destroy', $template));
        $this->assertDatabaseMissing('comm_templates', ['id' => $template->id]);
    }
}
