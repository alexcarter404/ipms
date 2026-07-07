<?php

namespace Tests\Feature;

use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentCategory;
use App\Models\Client;
use App\Models\Matter;
use App\Models\PortalUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private PortalUser $portalUser;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->client = Client::factory()->create();
        $this->matter = Matter::factory()->create(['client_id' => $this->client->id]);
        $this->portalUser = PortalUser::create([
            'client_id' => $this->client->id,
            'name' => 'Portal Person', 'email' => 'portal@client.example',
            'password' => 'secret-password',
        ]);
    }

    public function test_portal_users_sign_in_and_see_only_their_portfolio(): void
    {
        $stranger = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->post(route('portal.login.attempt'), [
            'email' => 'portal@client.example', 'password' => 'secret-password',
        ])->assertRedirect(route('portal.dashboard'));

        $this->actingAs($this->portalUser, 'portal')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('clientName', $this->client->name)
                ->has('matters', 1)
                ->where('matters.0.reference', $this->matter->reference)
                ->where('matters', fn ($matters) => collect($matters)->pluck('reference')->doesntContain($stranger->reference)));
    }

    public function test_bad_credentials_and_guests_are_kept_out(): void
    {
        $this->post(route('portal.login.attempt'), [
            'email' => 'portal@client.example', 'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));

        // A real portal session opens the portal, not the firm's system
        $this->post(route('portal.login.attempt'), [
            'email' => 'portal@client.example', 'password' => 'secret-password',
        ]);
        $this->get(route('portal.dashboard'))->assertOk();
        $this->get(route('matters.index'))->assertRedirect(route('login'));
    }

    public function test_renewals_are_instructed_from_the_portal(): void
    {
        $renewal = $this->matter->renewals()->create([
            'cycle' => 5, 'due_date' => now()->addMonths(2), 'status' => 'upcoming',
        ]);
        $lapse = $this->matter->renewals()->create([
            'cycle' => 6, 'due_date' => now()->addMonths(14), 'status' => 'upcoming',
        ]);

        $this->actingAs($this->portalUser, 'portal')
            ->post(route('portal.renewals.instruct', $renewal), ['decision' => 'pay'])
            ->assertSessionHas('success');
        $this->assertSame('instructed', $renewal->fresh()->status->value);
        $this->assertNotNull($renewal->fresh()->instructed_at);

        $this->actingAs($this->portalUser, 'portal')
            ->post(route('portal.renewals.instruct', $lapse), ['decision' => 'abandon']);
        $this->assertSame('waived', $lapse->fresh()->status->value);

        // Already-instructed renewals can't be flipped again
        $this->actingAs($this->portalUser, 'portal')
            ->post(route('portal.renewals.instruct', $renewal), ['decision' => 'abandon'])
            ->assertSessionHas('error');
        $this->assertSame('instructed', $renewal->fresh()->status->value);
    }

    public function test_other_clients_records_are_out_of_reach(): void
    {
        $otherMatter = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);
        $otherRenewal = $otherMatter->renewals()->create([
            'cycle' => 2, 'due_date' => now()->addMonth(), 'status' => 'upcoming',
        ]);
        $otherDocument = app(StoreDocument::class)->fromContent($otherMatter, 'secret.pdf', 'secret', [
            'category' => DocumentCategory::Other,
        ]);

        $this->actingAs($this->portalUser, 'portal')
            ->post(route('portal.renewals.instruct', $otherRenewal), ['decision' => 'pay'])
            ->assertForbidden();
        $this->actingAs($this->portalUser, 'portal')
            ->get(route('portal.documents.download', $otherDocument))
            ->assertForbidden();
    }

    public function test_own_documents_download_through_the_portal(): void
    {
        $document = app(StoreDocument::class)->fromContent($this->matter, 'spec.pdf', '%PDF ours', [
            'category' => DocumentCategory::FiledDocument,
        ]);

        $this->actingAs($this->portalUser, 'portal')
            ->get(route('portal.documents.download', $document))
            ->assertOk()
            ->assertDownload('spec.pdf');
    }

    public function test_admins_grant_and_revoke_portal_access(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('clients.portal-users.store', $this->client), [
                'name' => 'New Login', 'email' => 'new@client.example', 'password' => 'longenough',
            ])
            ->assertSessionHas('success');
        $this->assertSame(2, $this->client->portalUsers()->count());

        $professional = User::factory()->create(['access_role' => 'professional']);
        $this->actingAs($professional)
            ->post(route('clients.portal-users.store', $this->client), [
                'name' => 'Nope', 'email' => 'nope@client.example', 'password' => 'longenough',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('portal-users.destroy', $this->portalUser))
            ->assertSessionHas('success');
        $this->assertNull(PortalUser::find($this->portalUser->id));
    }
}
