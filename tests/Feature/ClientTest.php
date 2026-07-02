<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_clients_index_is_displayed(): void
    {
        Client::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Clients/Index')
                ->has('clients.data', 3));
    }

    public function test_clients_index_can_be_searched(): void
    {
        Client::factory()->create(['name' => 'Acme Industries', 'code' => 'ACME']);
        Client::factory()->create(['name' => 'Other Corp', 'code' => 'OTHR']);

        $this->actingAs($this->user)
            ->get(route('clients.index', ['search' => 'acme']))
            ->assertInertia(fn ($page) => $page->has('clients.data', 1));
    }

    public function test_client_can_be_created(): void
    {
        $response = $this->actingAs($this->user)->post(route('clients.store'), [
            'code' => 'ACME',
            'name' => 'Acme Industries Ltd',
            'type' => 'company',
            'email' => 'legal@acme.example',
        ]);

        $client = Client::firstWhere('code', 'ACME');
        $this->assertNotNull($client);
        $response->assertRedirect(route('clients.show', $client));
    }

    public function test_client_code_must_be_unique(): void
    {
        Client::factory()->create(['code' => 'ACME']);

        $this->actingAs($this->user)
            ->post(route('clients.store'), [
                'code' => 'ACME',
                'name' => 'Duplicate',
                'type' => 'company',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_client_can_be_updated(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->user)
            ->patch(route('clients.update', $client), [
                'code' => $client->code,
                'name' => 'Renamed Ltd',
                'type' => 'company',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertSame('Renamed Ltd', $client->fresh()->name);
    }

    public function test_client_with_matters_cannot_be_deleted(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)
            ->from(route('clients.show', $matter->client_id))
            ->delete(route('clients.destroy', $matter->client_id));

        $this->assertNotNull(Client::find($matter->client_id));
    }

    public function test_client_without_matters_can_be_deleted(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted($client);
    }

    public function test_contact_can_be_added_to_client(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->user)->post(route('clients.contacts.store', $client), [
            'name' => 'Sarah Bennett',
            'email' => 'sarah@example.com',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('contacts', [
            'client_id' => $client->id,
            'name' => 'Sarah Bennett',
            'is_primary' => true,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }
}
