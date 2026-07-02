<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Matter;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'reference' => 'P-2026-0001',
            'matter_type' => 'patent',
            'title' => 'Self-sealing valve assembly',
            'client_id' => Client::factory()->create()->id,
            'country_code' => 'GB',
            'status' => 'filed',
            'application_no' => 'GB2601234.5',
            'application_date' => '2026-01-15',
        ], $overrides);
    }

    public function test_matters_index_is_displayed_with_filters(): void
    {
        Matter::factory()->count(2)->create();
        Matter::factory()->trademark()->create();

        $this->actingAs($this->user)
            ->get(route('matters.index', ['type' => 'trademark']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Matters/Index')
                ->has('matters.data', 1));
    }

    public function test_matters_can_be_searched_by_reference_and_client(): void
    {
        $client = Client::factory()->create(['name' => 'NovaTech GmbH']);
        Matter::factory()->create(['reference' => 'P-1111-0001', 'client_id' => $client->id]);
        Matter::factory()->create(['reference' => 'P-2222-0001']);

        $this->actingAs($this->user)
            ->get(route('matters.index', ['search' => 'NovaTech']))
            ->assertInertia(fn ($page) => $page->has('matters.data', 1));

        $this->actingAs($this->user)
            ->get(route('matters.index', ['search' => 'P-2222']))
            ->assertInertia(fn ($page) => $page->has('matters.data', 1));
    }

    public function test_matter_can_be_created(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('matters.store'), $this->validPayload());

        $matter = Matter::firstWhere('reference', 'P-2026-0001');
        $this->assertNotNull($matter);
        $this->assertSame('patent', $matter->matter_type->value);
        $response->assertRedirect(route('matters.show', $matter));
    }

    public function test_matter_reference_must_be_unique(): void
    {
        Matter::factory()->create(['reference' => 'P-2026-0001']);

        $this->actingAs($this->user)
            ->post(route('matters.store'), $this->validPayload())
            ->assertSessionHasErrors('reference');
    }

    public function test_matter_requires_valid_type_and_status(): void
    {
        $this->actingAs($this->user)
            ->post(route('matters.store'), $this->validPayload([
                'matter_type' => 'starship',
                'status' => 'warp',
            ]))
            ->assertSessionHasErrors(['matter_type', 'status']);
    }

    public function test_matter_show_page_renders_with_relations(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)
            ->get(route('matters.show', $matter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Matters/Show')
                ->where('matter.id', $matter->id)
                ->has('workflows')
                ->has('templates')
                ->has('partyRoles'));
    }

    public function test_matter_can_be_updated(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)->patch(route('matters.update', $matter), $this->validPayload([
            'reference' => $matter->reference,
            'client_id' => $matter->client_id,
            'status' => 'granted',
            'registration_no' => '9,876,543',
            'registration_date' => '2026-06-01',
        ]));

        $matter->refresh();
        $this->assertSame('granted', $matter->status->value);
        $this->assertSame('9,876,543', $matter->registration_no);
    }

    public function test_matter_can_be_soft_deleted(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('matters.destroy', $matter))
            ->assertRedirect(route('matters.index'));

        $this->assertSoftDeleted($matter);
    }

    public function test_existing_party_can_be_attached_with_role(): void
    {
        $matter = Matter::factory()->create();
        $party = Party::factory()->create();

        $this->actingAs($this->user)->post(route('matters.parties.store', $matter), [
            'party_id' => $party->id,
            'role' => 'inventor',
        ]);

        $this->assertDatabaseHas('matter_party', [
            'matter_id' => $matter->id,
            'party_id' => $party->id,
            'role' => 'inventor',
        ]);
    }

    public function test_new_party_can_be_created_and_attached_inline(): void
    {
        $matter = Matter::factory()->create();

        $this->actingAs($this->user)->post(route('matters.parties.store', $matter), [
            'name' => 'Dr Jane Inventor',
            'party_type' => 'individual',
            'role' => 'inventor',
        ]);

        $party = Party::firstWhere('name', 'Dr Jane Inventor');
        $this->assertNotNull($party);
        $this->assertDatabaseHas('matter_party', [
            'matter_id' => $matter->id,
            'party_id' => $party->id,
            'role' => 'inventor',
        ]);
    }

    public function test_duplicate_party_role_is_rejected(): void
    {
        $matter = Matter::factory()->create();
        $party = Party::factory()->create();
        $matter->parties()->attach($party, ['role' => 'inventor']);

        $this->actingAs($this->user)
            ->from(route('matters.show', $matter))
            ->post(route('matters.parties.store', $matter), [
                'party_id' => $party->id,
                'role' => 'inventor',
            ]);

        $this->assertSame(1, $matter->parties()->wherePivot('role', 'inventor')->count());
    }

    public function test_party_can_be_detached_by_role(): void
    {
        $matter = Matter::factory()->create();
        $party = Party::factory()->create();
        $matter->parties()->attach($party, ['role' => 'inventor']);
        $matter->parties()->attach($party, ['role' => 'applicant']);

        $this->actingAs($this->user)->delete(
            route('matters.parties.destroy', [$matter, $party]),
            ['role' => 'inventor']
        );

        $this->assertSame(0, $matter->parties()->wherePivot('role', 'inventor')->count());
        $this->assertSame(1, $matter->parties()->wherePivot('role', 'applicant')->count());
    }

    public function test_nice_class_can_be_added_to_matter(): void
    {
        $matter = Matter::factory()->trademark()->create();

        $this->actingAs($this->user)->post(route('matters.classes.store', $matter), [
            'class_number' => 9,
            'specification' => 'Computer software.',
        ]);

        $this->assertDatabaseHas('matter_classes', [
            'matter_id' => $matter->id,
            'class_number' => 9,
        ]);
    }

    public function test_duplicate_class_number_is_rejected(): void
    {
        $matter = Matter::factory()->trademark()->create();
        $matter->classes()->create(['class_number' => 9]);

        $this->actingAs($this->user)
            ->post(route('matters.classes.store', $matter), ['class_number' => 9])
            ->assertSessionHasErrors('class_number');
    }
}
