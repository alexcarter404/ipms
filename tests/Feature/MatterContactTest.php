<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CommTemplate;
use App\Models\Contact;
use App\Models\Matter;
use App\Models\User;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterContactTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_existing_client_contact_can_be_linked_with_role(): void
    {
        $matter = Matter::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $matter->client_id]);

        $this->actingAs($this->user)->post(route('matters.contacts.store', $matter), [
            'contact_id' => $contact->id,
            'role' => 'main',
        ]);

        $this->assertDatabaseHas('matter_contact', [
            'matter_id' => $matter->id,
            'contact_id' => $contact->id,
            'role' => 'main',
        ]);
    }

    public function test_docketing_mailbox_can_be_created_and_linked_inline(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)->post(route('matters.contacts.store', $matter), [
            'name' => 'Acme IP Docketing',
            'contact_type' => 'mailbox',
            'email' => 'docketing@acme.example',
            'role' => 'docketing',
        ]);

        $contact = Contact::firstWhere('name', 'Acme IP Docketing');
        $this->assertSame('mailbox', $contact->type->value);
        $this->assertSame($matter->client_id, $contact->client_id);
        $this->assertDatabaseHas('matter_contact', [
            'matter_id' => $matter->id,
            'contact_id' => $contact->id,
            'role' => 'docketing',
        ]);
    }

    public function test_mailbox_contacts_require_an_email(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)->post(route('matters.contacts.store', $matter), [
            'name' => 'Docketing Inbox',
            'contact_type' => 'mailbox',
            'role' => 'docketing',
        ])->assertSessionHasErrors('email');
    }

    public function test_contact_of_another_client_cannot_be_linked(): void
    {
        $matter = Matter::factory()->create();
        $foreign = Contact::factory()->create(); // belongs to a different client

        $this->actingAs($this->user)->post(route('matters.contacts.store', $matter), [
            'contact_id' => $foreign->id,
            'role' => 'main',
        ])->assertSessionHasErrors('contact_id');
    }

    public function test_same_contact_can_hold_multiple_roles_but_not_duplicates(): void
    {
        $matter = Matter::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $matter->client_id]);
        $matter->contacts()->attach($contact->id, ['role' => 'main']);

        // A second role is fine
        $this->actingAs($this->user)->post(route('matters.contacts.store', $matter), [
            'contact_id' => $contact->id,
            'role' => 'billing',
        ]);
        $this->assertSame(2, $matter->contacts()->count());

        // The same role again is rejected
        $this->actingAs($this->user)
            ->from(route('matters.show', $matter))
            ->post(route('matters.contacts.store', $matter), [
                'contact_id' => $contact->id,
                'role' => 'billing',
            ])
            ->assertSessionHas('error');
        $this->assertSame(2, $matter->contacts()->count());
    }

    public function test_contact_can_be_unlinked_by_role_only(): void
    {
        $matter = Matter::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $matter->client_id]);
        $matter->contacts()->attach($contact->id, ['role' => 'main']);
        $matter->contacts()->attach($contact->id, ['role' => 'billing']);

        $this->actingAs($this->user)->delete(
            route('matters.contacts.destroy', [$matter, $contact]),
            ['role' => 'billing']
        );

        $this->assertSame(1, $matter->contacts()->count());
        $this->assertSame('main', $matter->contacts()->first()->pivot->role);
    }

    public function test_merge_fields_use_main_contact_and_docketing_contact(): void
    {
        $matter = Matter::factory()->create();
        $main = Contact::factory()->create(['client_id' => $matter->client_id, 'name' => 'Sarah Bennett']);
        $docketing = Contact::factory()->mailbox()->create([
            'client_id' => $matter->client_id,
            'name' => 'Acme IP Docketing',
            'email' => 'ip-docketing@acme.example',
        ]);
        $matter->contacts()->attach($main->id, ['role' => 'main']);
        $matter->contacts()->attach($docketing->id, ['role' => 'docketing']);

        $template = CommTemplate::factory()->create([
            'body' => 'Dear {{contact.name}}, please copy {{docketing.email}}.',
        ]);

        $rendered = app(TemplateRenderer::class)->render($template, $matter);

        $this->assertStringContainsString('Dear Sarah Bennett', $rendered['body']);
        $this->assertStringContainsString('copy ip-docketing@acme.example', $rendered['body']);
    }

    public function test_contact_merge_field_falls_back_to_client_primary_contact(): void
    {
        $client = Client::factory()->create();
        $primary = Contact::factory()->create([
            'client_id' => $client->id,
            'name' => 'Primary Person',
            'is_primary' => true,
        ]);
        $matter = Matter::factory()->create(['client_id' => $client->id]); // no linked contacts

        $template = CommTemplate::factory()->create(['body' => 'Dear {{contact.name}},']);

        $rendered = app(TemplateRenderer::class)->render($template, $matter);

        $this->assertStringContainsString('Dear Primary Person', $rendered['body']);
    }

    public function test_client_contact_form_accepts_types_and_requires_mailbox_email(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->user)->post(route('clients.contacts.store', $client), [
            'name' => 'General Enquiries',
            'type' => 'mailbox',
            'email' => 'info@client.example',
        ]);

        $this->assertDatabaseHas('contacts', [
            'client_id' => $client->id,
            'name' => 'General Enquiries',
            'type' => 'mailbox',
        ]);

        $this->actingAs($this->user)->post(route('clients.contacts.store', $client), [
            'name' => 'Broken Mailbox',
            'type' => 'mailbox',
        ])->assertSessionHasErrors('email');
    }

    public function test_matter_show_exposes_linked_contacts_and_client_contacts(): void
    {
        $matter = Matter::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $matter->client_id]);
        $matter->contacts()->attach($contact->id, ['role' => 'main']);

        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertInertia(fn ($page) => $page
                ->has('matter.contacts', 1)
                ->where('matter.contacts.0.pivot.role', 'main')
                ->has('clientContacts', 1)
                ->has('contactRoles'));
    }
}
