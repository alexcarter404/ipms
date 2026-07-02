<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\CommTemplate;
use App\Models\Contact;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\Party;
use App\Models\Workflow;
use App\Repositories\ClientEntityRepository;
use App\Repositories\ClientRepository;
use App\Repositories\CommTemplateRepository;
use App\Repositories\ContactRepository;
use App\Repositories\MatterRepository;
use App\Repositories\PartyRepository;
use App\Repositories\TaskRepository;
use App\Repositories\WorkflowRepository;

/**
 * Global typeahead search: grouped, lightweight results with a
 * destination URL per hit; each group is capped so the dropdown stays
 * scannable.
 */
class SearchService
{
    private const PER_GROUP = 5;

    public function __construct(
        private MatterRepository $matters,
        private ClientRepository $clients,
        private ContactRepository $contacts,
        private ClientEntityRepository $entities,
        private PartyRepository $parties,
        private TaskRepository $tasks,
        private WorkflowRepository $workflows,
        private CommTemplateRepository $templates,
    ) {
    }

    /** @return array<int, array{type: string, items: mixed}> */
    public function search(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';

        return array_values(array_filter([
            [
                'type' => 'Matters',
                'items' => $this->matters->searchTypeahead($like, self::PER_GROUP)->map(fn (Matter $m) => [
                    'label' => "{$m->reference} — {$m->title}",
                    'sublabel' => trim(($m->client->name ?? '').' · '.$m->country_code.' · '.$m->status->label(), ' ·'),
                    'url' => route('matters.show', $m),
                ]),
            ],
            [
                'type' => 'Clients',
                'items' => $this->clients->searchTypeahead($like, self::PER_GROUP)->map(fn (Client $c) => [
                    'label' => $c->name,
                    'sublabel' => $c->code,
                    'url' => route('clients.show', $c),
                ]),
            ],
            [
                'type' => 'Contacts',
                'items' => $this->contacts->searchTypeahead($like, self::PER_GROUP)->map(fn (Contact $c) => [
                    'label' => $c->name,
                    'sublabel' => trim(($c->email ?? '').' · '.($c->client->name ?? '').' · '.$c->type->label(), ' ·'),
                    'url' => route('clients.show', $c->client_id),
                ]),
            ],
            [
                'type' => 'Entities',
                'items' => $this->entities->searchTypeahead($like, self::PER_GROUP)->map(fn (ClientEntity $e) => [
                    'label' => $e->name,
                    'sublabel' => trim('Entity of '.($e->client->name ?? '').($e->is_default ? ' · default' : '')),
                    'url' => route('clients.show', $e->client_id),
                ]),
            ],
            [
                'type' => 'Parties',
                'items' => $this->parties->searchTypeahead($like, self::PER_GROUP)->map(fn (Party $p) => [
                    'label' => $p->name,
                    'sublabel' => "Party on {$p->matters_count} matter(s)",
                    'url' => route('matters.show', $p->matters->first()->id),
                ]),
            ],
            [
                'type' => 'Tasks',
                'items' => $this->tasks->searchTypeahead($like, self::PER_GROUP)->map(fn (MatterTask $t) => [
                    'label' => $t->title,
                    'sublabel' => trim(($t->matter->reference ?? '').' · due '.$t->due_date->format('j M Y').' · '.$t->status->label(), ' ·'),
                    'url' => route('matters.show', $t->matter_id),
                ]),
            ],
            [
                'type' => 'Workflows',
                'items' => $this->workflows->searchTypeahead($like, self::PER_GROUP)->map(fn (Workflow $w) => [
                    'label' => $w->name,
                    'sublabel' => 'Workflow · trigger: '.$w->trigger_event->label(),
                    'url' => route('workflows.edit', $w),
                ]),
            ],
            [
                'type' => 'Templates',
                'items' => $this->templates->searchTypeahead($like, self::PER_GROUP)->map(fn (CommTemplate $t) => [
                    'label' => $t->name,
                    'sublabel' => 'Template · '.$t->channel,
                    'url' => route('templates.edit', $t),
                ]),
            ],
        ], fn ($group) => count($group['items']) > 0));
    }
}
