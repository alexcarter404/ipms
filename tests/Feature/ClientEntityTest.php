<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\CommTemplate;
use App\Models\Matter;
use App\Models\User;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientEntityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_creating_a_client_creates_a_default_entity(): void
    {
        $this->actingAs($this->user)->post(route('clients.store'), [
            'code' => 'ACME',
            'name' => 'Acme Industries Ltd',
            'type' => 'company',
            'email' => 'legal@acme.example',
            'country_code' => 'GB',
        ]);

        $client = Client::firstWhere('code', 'ACME');
        $entity = $client->defaultEntity();

        $this->assertNotNull($entity);
        $this->assertTrue($entity->is_default);
        $this->assertSame('Acme Industries Ltd', $entity->name);
        $this->assertSame('legal@acme.example', $entity->billing_email);
    }

    public function test_additional_entity_can_be_added_with_billing_details(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->user)->post(route('clients.entities.store', $client), [
            'name' => 'Acme Industries Inc',
            'country_code' => 'US',
            'billing_contact_name' => 'US Accounts',
            'billing_email' => 'us-invoices@acme.example',
            'billing_address' => 'PO Box 4410, Wilmington DE',
            'billing_reference' => 'PO-2026-001',
        ]);

        $this->assertDatabaseHas('client_entities', [
            'client_id' => $client->id,
            'name' => 'Acme Industries Inc',
            'billing_email' => 'us-invoices@acme.example',
            'is_default' => false,
        ]);
        // The original default is untouched
        $this->assertSame(1, $client->entities()->where('is_default', true)->count());
    }

    public function test_making_an_entity_default_demotes_the_previous_default(): void
    {
        $client = Client::factory()->create();
        $original = $client->defaultEntity();
        $second = ClientEntity::factory()->create(['client_id' => $client->id]);

        $this->actingAs($this->user)->patch(route('entities.update', $second), [
            'name' => $second->name,
            'is_default' => true,
        ]);

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($original->fresh()->is_default);
        $this->assertSame(1, $client->entities()->where('is_default', true)->count());
    }

    public function test_default_entity_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $default = $client->defaultEntity();

        $this->actingAs($this->user)
            ->from(route('clients.show', $client))
            ->delete(route('entities.destroy', $default))
            ->assertSessionHas('error');

        $this->assertNotNull($default->fresh());
    }

    public function test_entity_with_matters_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $entity = ClientEntity::factory()->create(['client_id' => $client->id]);
        Matter::factory()->create([
            'client_id' => $client->id,
            'client_entity_id' => $entity->id,
        ]);

        $this->actingAs($this->user)
            ->from(route('clients.show', $client))
            ->delete(route('entities.destroy', $entity))
            ->assertSessionHas('error');

        $this->assertNotNull($entity->fresh());
    }

    public function test_non_default_entity_without_matters_can_be_deleted(): void
    {
        $client = Client::factory()->create();
        $entity = ClientEntity::factory()->create(['client_id' => $client->id]);

        $this->actingAs($this->user)->delete(route('entities.destroy', $entity));

        $this->assertDatabaseMissing('client_entities', ['id' => $entity->id]);
    }

    public function test_matter_can_be_assigned_an_entity_of_its_client(): void
    {
        $client = Client::factory()->create();
        $entity = ClientEntity::factory()->create(['client_id' => $client->id]);
        $matter = Matter::factory()->create(['client_id' => $client->id]);

        $this->actingAs($this->user)->patch(route('matters.update', $matter), [
            'reference' => $matter->reference,
            'matter_type' => $matter->matter_type->value,
            'title' => $matter->title,
            'client_id' => $client->id,
            'client_entity_id' => $entity->id,
            'country_code' => $matter->country_code,
            'status' => $matter->status->value,
        ]);

        $this->assertSame($entity->id, $matter->fresh()->client_entity_id);
    }

    public function test_matter_rejects_entity_belonging_to_another_client(): void
    {
        $matter = Matter::factory()->create();
        $foreignEntity = ClientEntity::factory()->create(); // different client

        $this->actingAs($this->user)->patch(route('matters.update', $matter), [
            'reference' => $matter->reference,
            'matter_type' => $matter->matter_type->value,
            'title' => $matter->title,
            'client_id' => $matter->client_id,
            'client_entity_id' => $foreignEntity->id,
            'country_code' => $matter->country_code,
            'status' => $matter->status->value,
        ])->assertSessionHasErrors('client_entity_id');
    }

    public function test_effective_billing_entity_falls_back_to_client_default(): void
    {
        $client = Client::factory()->create();
        $second = ClientEntity::factory()->create(['client_id' => $client->id]);

        $unassigned = Matter::factory()->create(['client_id' => $client->id]);
        $assigned = Matter::factory()->create([
            'client_id' => $client->id,
            'client_entity_id' => $second->id,
        ]);

        $this->assertTrue($unassigned->effectiveBillingEntity()->is_default);
        $this->assertSame($second->id, $assigned->effectiveBillingEntity()->id);
    }

    public function test_matter_show_reports_billing_entity_with_fallback_flag(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertInertia(fn ($page) => $page
                ->where('billingEntity.is_fallback', true)
                ->where('billingEntity.name', $matter->client->name));
    }

    public function test_entity_merge_fields_render_in_communications(): void
    {
        $client = Client::factory()->create();
        $entity = ClientEntity::factory()->create([
            'client_id' => $client->id,
            'name' => 'Acme Industries Inc',
            'vat_number' => 'US-9988',
            'billing_email' => 'us-invoices@acme.example',
            'billing_address' => 'PO Box 4410',
            'billing_reference' => 'PO-77',
        ]);
        $matter = Matter::factory()->create([
            'client_id' => $client->id,
            'client_entity_id' => $entity->id,
        ]);

        $template = CommTemplate::factory()->create([
            'subject' => 'Invoice for {{entity.name}}',
            'body' => 'Bill to: {{entity.billing_address}} ({{entity.billing_email}}), ref {{entity.billing_reference}}, VAT {{entity.vat_number}}',
        ]);

        $rendered = app(TemplateRenderer::class)->render($template, $matter);

        $this->assertSame('Invoice for Acme Industries Inc', $rendered['subject']);
        $this->assertStringContainsString('Bill to: PO Box 4410 (us-invoices@acme.example)', $rendered['body']);
        $this->assertStringContainsString('ref PO-77, VAT US-9988', $rendered['body']);
    }

    public function test_entity_billing_address_falls_back_to_registered_address(): void
    {
        $entity = ClientEntity::factory()->create([
            'address' => '1 Innovation Way, Cambridge',
            'billing_address' => null,
        ]);

        $this->assertSame('1 Innovation Way, Cambridge', $entity->effectiveBillingAddress());
    }
}
