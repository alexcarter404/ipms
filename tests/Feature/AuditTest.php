<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // PHPUnit runs in a console context, where auditing is off by default
        config()->set('audit.console', true);
        $this->user = User::factory()->create();
    }

    public function test_model_changes_are_audited_with_user_attribution(): void
    {
        $this->actingAs($this->user);

        $client = Client::create(['code' => 'AUD', 'name' => 'Audited Ltd', 'type' => 'company']);
        $client->update(['name' => 'Audited Holdings Ltd']);

        $audits = Audit::where('auditable_type', Client::class)
            ->where('auditable_id', $client->id)
            ->oldest('id')
            ->get();

        $this->assertCount(2, $audits);
        $this->assertSame('created', $audits[0]->event);
        $this->assertSame('updated', $audits[1]->event);
        $this->assertSame($this->user->id, $audits[1]->user_id);
        $this->assertSame('Audited Ltd', $audits[1]->old_values['name']);
        $this->assertSame('Audited Holdings Ltd', $audits[1]->new_values['name']);
    }

    public function test_credentials_never_reach_the_audit_log(): void
    {
        $this->actingAs($this->user);

        $this->user->update([
            'name' => 'Renamed User',
            'password' => bcrypt('a-new-secret'),
            'remember_token' => 'token-123',
        ]);

        $audit = Audit::where('auditable_type', User::class)
            ->where('auditable_id', $this->user->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame('Renamed User', $audit->new_values['name']);
        $this->assertArrayNotHasKey('password', $audit->new_values);
        $this->assertArrayNotHasKey('remember_token', $audit->new_values);
        $this->assertArrayNotHasKey('password', $audit->old_values);
    }

    public function test_an_update_audit_can_be_rolled_back_and_forward(): void
    {
        $this->actingAs($this->user);

        $matter = Matter::factory()->create(['title' => 'Original title']);
        $matter->update(['title' => 'Amended title']);

        $audit = Audit::where('auditable_type', Matter::class)
            ->where('auditable_id', $matter->id)
            ->where('event', 'updated')
            ->firstOrFail();

        // Roll back to the pre-change state
        $this->post(route('audits.transition', $audit), ['direction' => 'back'])
            ->assertSessionHas('success');
        $this->assertSame('Original title', $matter->fresh()->title);

        // ...and forward to the post-change state again
        $this->post(route('audits.transition', $audit), ['direction' => 'forward'])
            ->assertSessionHas('success');
        $this->assertSame('Amended title', $matter->fresh()->title);

        // The transitions themselves were audited, keeping the trail honest
        $this->assertSame(4, Audit::where('auditable_type', Matter::class)
            ->where('auditable_id', $matter->id)->count());
    }

    public function test_transition_guards(): void
    {
        $this->actingAs($this->user);

        $matter = Matter::factory()->create();
        $created = Audit::where('auditable_type', Matter::class)
            ->where('auditable_id', $matter->id)
            ->where('event', 'created')
            ->firstOrFail();

        // A created entry has no before/after pair to travel between
        $this->post(route('audits.transition', $created), ['direction' => 'back'])
            ->assertSessionHas('error');

        // A deleted record can't be restored through its audit trail
        $matter->update(['title' => 'Changed']);
        $updated = Audit::where('auditable_type', Matter::class)
            ->where('auditable_id', $matter->id)
            ->where('event', 'updated')
            ->firstOrFail();
        $matter->delete();

        $this->post(route('audits.transition', $updated), ['direction' => 'back'])
            ->assertSessionHas('error');
    }

    public function test_the_matter_history_includes_its_children(): void
    {
        $this->actingAs($this->user);

        $matter = Matter::factory()->create();
        $task = $matter->tasks()->create([
            'title' => 'File response', 'due_date' => now()->addMonth(),
            'status' => 'pending', 'priority' => 'normal',
        ]);
        $task->update(['status' => 'completed']);

        $this->get(route('matters.show', $matter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('audits', 3) // matter created, task created, task updated
                ->where('audits.0.subject_type', 'Task')
                ->where('audits.0.event', 'updated')
                ->where('audits.0.user', $this->user->name)
                ->where('audits.0.can_transition', true)
                ->where('audits.0.changes.0.field', 'status')
                ->where('audits.0.changes.0.new', 'completed'));
    }

    public function test_the_client_history_includes_its_entities(): void
    {
        $this->actingAs($this->user);

        $client = Client::factory()->create();
        $entity = ClientEntity::factory()->create(['client_id' => $client->id, 'name' => 'Acme GmbH']);
        $entity->update(['country_code' => 'DE']);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('audits')
                ->where('audits.0.subject_type', 'Entity')
                ->where('audits.0.subject_label', 'Acme GmbH'));
    }
}
