<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\CommTemplate;
use App\Models\Contact;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\Party;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global typeahead search. Returns grouped, lightweight results with a
 * destination URL per hit; each group is capped so the dropdown stays
 * scannable.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $groups = array_values(array_filter([
            $this->matters($like),
            $this->clients($like),
            $this->contacts($like),
            $this->entities($like),
            $this->parties($like),
            $this->tasks($like),
            $this->workflows($like),
            $this->templates($like),
        ], fn ($group) => count($group['items']) > 0));

        return response()->json(['groups' => $groups]);
    }

    private function matters(string $like): array
    {
        $items = Matter::query()
            ->with('client:id,name')
            ->where(fn ($w) => $w
                ->where('reference', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('application_no', 'like', $like)
                ->orWhere('registration_no', 'like', $like))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Matter $m) => [
                'label' => "{$m->reference} — {$m->title}",
                'sublabel' => trim(($m->client->name ?? '').' · '.$m->country_code.' · '.$m->status->label(), ' ·'),
                'url' => route('matters.show', $m),
            ]);

        return ['type' => 'Matters', 'items' => $items];
    }

    private function clients(string $like): array
    {
        $items = Client::query()
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Client $c) => [
                'label' => $c->name,
                'sublabel' => $c->code,
                'url' => route('clients.show', $c),
            ]);

        return ['type' => 'Clients', 'items' => $items];
    }

    private function contacts(string $like): array
    {
        $items = Contact::query()
            ->with('client:id,name')
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Contact $c) => [
                'label' => $c->name,
                'sublabel' => trim(($c->email ?? '').' · '.($c->client->name ?? '').' · '.$c->type->label(), ' ·'),
                'url' => route('clients.show', $c->client_id),
            ]);

        return ['type' => 'Contacts', 'items' => $items];
    }

    private function entities(string $like): array
    {
        $items = ClientEntity::query()
            ->with('client:id,name')
            ->where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('vat_number', 'like', $like)
                ->orWhere('registration_no', 'like', $like))
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (ClientEntity $e) => [
                'label' => $e->name,
                'sublabel' => trim('Entity of '.($e->client->name ?? '').($e->is_default ? ' · default' : '')),
                'url' => route('clients.show', $e->client_id),
            ]);

        return ['type' => 'Entities', 'items' => $items];
    }

    private function parties(string $like): array
    {
        $items = Party::query()
            ->with(['matters' => fn ($q) => $q->select('matters.id')->limit(1)])
            ->withCount('matters')
            ->where('name', 'like', $like)
            ->whereHas('matters')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Party $p) => [
                'label' => $p->name,
                'sublabel' => "Party on {$p->matters_count} matter(s)",
                'url' => route('matters.show', $p->matters->first()->id),
            ]);

        return ['type' => 'Parties', 'items' => $items];
    }

    private function tasks(string $like): array
    {
        $items = MatterTask::query()
            ->with('matter:id,reference')
            ->whereNotNull('matter_id')
            ->where('title', 'like', $like)
            ->orderBy('due_date')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (MatterTask $t) => [
                'label' => $t->title,
                'sublabel' => trim(($t->matter->reference ?? '').' · due '.$t->due_date->format('j M Y').' · '.$t->status->label(), ' ·'),
                'url' => route('matters.show', $t->matter_id),
            ]);

        return ['type' => 'Tasks', 'items' => $items];
    }

    private function workflows(string $like): array
    {
        $items = Workflow::query()
            ->where('name', 'like', $like)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Workflow $w) => [
                'label' => $w->name,
                'sublabel' => 'Workflow · trigger: '.$w->trigger_event->label(),
                'url' => route('workflows.edit', $w),
            ]);

        return ['type' => 'Workflows', 'items' => $items];
    }

    private function templates(string $like): array
    {
        $items = CommTemplate::query()
            ->where('name', 'like', $like)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (CommTemplate $t) => [
                'label' => $t->name,
                'sublabel' => 'Template · '.$t->channel,
                'url' => route('templates.edit', $t),
            ]);

        return ['type' => 'Templates', 'items' => $items];
    }
}
