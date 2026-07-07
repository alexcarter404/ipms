<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Matter;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $accessRole): User
    {
        return User::factory()->create(['access_role' => $accessRole]);
    }

    public function test_read_only_users_browse_but_cannot_change_anything(): void
    {
        $reader = $this->user('readonly');
        $matter = Matter::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($reader)->get(route('matters.index'))->assertOk();
        $this->actingAs($reader)->get(route('matters.show', $matter))->assertOk();

        // Plain request: hard 403
        $this->actingAs($reader)
            ->post(route('matters.tasks.store', $matter), ['title' => 'Nope', 'due_date' => now()->addDay()->toDateString()])
            ->assertForbidden();

        // Inertia request: soft error flash back to the page
        $this->actingAs($reader)
            ->post(route('matters.tasks.store', $matter), ['title' => 'Nope', 'due_date' => now()->addDay()->toDateString()], ['X-Inertia' => 'true'])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'read-only'));

        $this->assertSame(0, $matter->tasks()->count());

        // Their own profile stays editable
        $this->actingAs($reader)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $reader->email])
            ->assertRedirect();
        $this->assertSame('New Name', $reader->fresh()->name);
    }

    public function test_only_admins_manage_configuration(): void
    {
        $professional = $this->user('professional');

        $this->actingAs($professional)->get(route('renewal-rules.index'))->assertOk();
        $this->actingAs($professional)
            ->post(route('renewal-rules.store'), ['name' => 'X'])
            ->assertForbidden();
        $this->actingAs($professional)->get(route('users.index'))->assertForbidden();

        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    public function test_finance_shares_billing_settings_but_not_admin_configuration(): void
    {
        $finance = $this->user('finance');

        $this->actingAs($finance)
            ->post(route('billing.tax-rates.store'), ['name' => 'VAT 5%', 'rate' => 5, 'country_code' => 'GB'])
            ->assertSessionHas('success');

        $this->actingAs($finance)->post(route('renewal-rules.store'), ['name' => 'X'])->assertForbidden();

        $professional = $this->user('professional');
        $this->actingAs($professional)
            ->post(route('billing.tax-rates.store'), ['name' => 'VAT 6%', 'rate' => 6, 'country_code' => 'GB'])
            ->assertForbidden();
    }

    public function test_walled_clients_are_invisible_to_outsiders(): void
    {
        $admin = $this->user('admin');
        $insider = $this->user('professional');
        $outsider = $this->user('professional');

        $walled = Client::factory()->create(['name' => 'Secret Project Client']);
        $matter = Matter::factory()->create(['client_id' => $walled->id, 'reference' => 'SEC-0001']);
        $open = Client::factory()->create(['name' => 'Open Client']);

        $this->actingAs($admin)
            ->put(route('clients.wall', $walled), ['user_ids' => [$insider->id]])
            ->assertSessionHas('success');

        // Index lists, show pages and search respect the wall
        $this->actingAs($outsider)->get(route('clients.index'))
            ->assertInertia(fn ($page) => $page
                ->where('clients.data', fn ($clients) => collect($clients)->pluck('name')->doesntContain('Secret Project Client')));
        $this->actingAs($outsider)->get(route('clients.show', $walled))->assertForbidden();
        $this->actingAs($outsider)->get(route('matters.show', $matter))->assertForbidden();
        $this->actingAs($outsider)->get(route('matters.index'))
            ->assertInertia(fn ($page) => $page
                ->where('matters.data', fn ($matters) => collect($matters)->pluck('reference')->doesntContain('SEC-0001')));
        $search = $this->actingAs($outsider)->get(route('search', ['q' => 'Secret Project']))->json();
        $this->assertSame([], array_filter($search['groups'], fn ($g) => $g['type'] === 'Clients' && count($g['items'])));

        // Wall members and admins see it
        $this->actingAs($insider)->get(route('clients.show', $walled))->assertOk();
        $this->actingAs($insider)->get(route('matters.show', $matter))->assertOk();
        $this->actingAs($admin)->get(route('clients.show', $walled))->assertOk();

        // Unwalled clients stay visible to everyone
        $this->actingAs($outsider)->get(route('clients.show', $open))->assertOk();
    }

    public function test_admins_manage_access_roles_but_the_last_admin_is_protected(): void
    {
        $admin = $this->user('admin');
        $colleague = $this->user('professional');

        $this->actingAs($admin)
            ->patch(route('users.update', $colleague), ['access_role' => 'finance', 'role' => 'case_manager'])
            ->assertSessionHas('success');
        $this->assertSame('finance', $colleague->fresh()->access_role->value);

        // The only admin cannot demote themselves
        $this->actingAs($admin)
            ->patch(route('users.update', $admin), ['access_role' => 'professional'])
            ->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->access_role->value);
    }

    public function test_conflict_check_searches_the_whole_practice(): void
    {
        $user = $this->user('professional');
        $client = Client::factory()->create(['name' => 'Meridian Industrial Ltd']);
        $client->contacts()->create(['name' => 'Meridian Docketing', 'type' => 'mailbox', 'email' => 'd@m.example']);
        Party::factory()->create(['name' => 'Meridian Holdings BV']);

        $matches = $this->actingAs($user)
            ->get(route('conflict-check', ['name' => 'Meridian']))
            ->assertOk()
            ->json('matches');

        $types = collect($matches)->pluck('type');
        $this->assertTrue($types->contains('Client'));
        $this->assertTrue($types->contains('Contact'));
        $this->assertTrue($types->contains('Party'));

        // Short terms don't fish
        $this->assertSame([], $this->actingAs($user)
            ->get(route('conflict-check', ['name' => 'Me']))
            ->json('matches'));
    }
}
