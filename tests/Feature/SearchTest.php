<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\CommTemplate;
use App\Models\Contact;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\Party;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function search(string $q): array
    {
        return $this->actingAs($this->user)
            ->getJson(route('search', ['q' => $q]))
            ->assertOk()
            ->json('groups');
    }

    private function group(array $groups, string $type): ?array
    {
        foreach ($groups as $group) {
            if ($group['type'] === $type) {
                return $group;
            }
        }

        return null;
    }

    public function test_search_requires_authentication(): void
    {
        $this->get(route('search', ['q' => 'acme']))->assertRedirect(route('login'));
    }

    public function test_queries_under_two_characters_return_nothing(): void
    {
        Matter::factory()->create(['reference' => 'P-1']);

        $this->assertSame([], $this->search('P'));
    }

    public function test_matters_are_found_by_reference_title_and_numbers(): void
    {
        $matter = Matter::factory()->create([
            'reference' => 'P-2026-0042',
            'title' => 'Self-sealing valve assembly',
            'application_no' => 'GB2601234.5',
        ]);

        foreach (['P-2026-0042', 'valve assembly', 'GB2601234'] as $term) {
            $group = $this->group($this->search($term), 'Matters');
            $this->assertNotNull($group, "no matter hit for '{$term}'");
            $this->assertStringContainsString('P-2026-0042', $group['items'][0]['label']);
            $this->assertSame(route('matters.show', $matter), $group['items'][0]['url']);
        }
    }

    public function test_clients_contacts_and_entities_are_found(): void
    {
        $client = Client::factory()->create(['name' => 'Acme Industries Ltd', 'code' => 'ACME']);
        Contact::factory()->mailbox()->create([
            'client_id' => $client->id,
            'name' => 'Acme IP Docketing',
            'email' => 'ip-docketing@acme.example',
        ]);
        ClientEntity::factory()->create([
            'client_id' => $client->id,
            'name' => 'Acme Industries Inc',
        ]);

        $groups = $this->search('acme');

        $this->assertNotNull($this->group($groups, 'Clients'));
        $this->assertNotNull($this->group($groups, 'Contacts'));
        $this->assertNotNull($this->group($groups, 'Entities'));

        // Contacts are also found by email
        $byEmail = $this->group($this->search('ip-docketing@'), 'Contacts');
        $this->assertSame('Acme IP Docketing', $byEmail['items'][0]['label']);
        $this->assertSame(route('clients.show', $client), $byEmail['items'][0]['url']);
    }

    public function test_parties_link_to_a_matter_they_appear_on(): void
    {
        $matter = Matter::factory()->create();
        $party = Party::factory()->create(['name' => 'Dr Jane Inventor']);
        $matter->parties()->attach($party, ['role' => 'inventor']);

        // Parties with no matters are omitted (nowhere to link)
        Party::factory()->create(['name' => 'Jane Unattached']);

        $group = $this->group($this->search('Jane'), 'Parties');

        $this->assertCount(1, $group['items']);
        $this->assertSame('Dr Jane Inventor', $group['items'][0]['label']);
        $this->assertSame(route('matters.show', $matter), $group['items'][0]['url']);
    }

    public function test_tasks_workflows_and_templates_are_found(): void
    {
        $task = MatterTask::factory()->create(['title' => 'File response to office action']);
        Workflow::factory()->create(['name' => 'Office Action Response']);
        CommTemplate::factory()->create(['name' => 'Office Action Report']);

        $groups = $this->search('office action');

        $this->assertSame(
            route('matters.show', $task->matter_id),
            $this->group($groups, 'Tasks')['items'][0]['url']
        );
        $this->assertNotNull($this->group($groups, 'Workflows'));
        $this->assertNotNull($this->group($groups, 'Templates'));
    }

    public function test_groups_are_capped_and_empty_groups_omitted(): void
    {
        Matter::factory()->count(8)->create(['title' => 'Widget improvement']);

        $groups = $this->search('widget improvement');

        $this->assertCount(1, $groups); // only the Matters group
        $this->assertCount(5, $groups[0]['items']);
    }

    public function test_like_wildcards_in_the_query_are_escaped(): void
    {
        Matter::factory()->create(['title' => 'Underscore test']);

        // '%' must not act as a match-everything wildcard
        $this->assertSame([], $this->search('%%%'));
    }
}
