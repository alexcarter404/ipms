<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientEntity;
use App\Models\Contact;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conflict search at intake: does this name already appear anywhere in
 * the practice — as a client, one of its entities, a contact, or an
 * opposing/related party on a matter?
 */
class ConflictCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('name', ''));

        if (mb_strlen($term) < 3) {
            return response()->json(['matches' => []]);
        }

        $like = '%'.str_replace(' ', '%', $term).'%';

        $matches = collect()
            ->concat(Client::where('name', 'like', $like)->limit(5)->get()
                ->map(fn ($c) => ['type' => 'Client', 'name' => $c->name, 'detail' => "Code {$c->code}"]))
            ->concat(ClientEntity::with('client:id,name')->where('name', 'like', $like)->limit(5)->get()
                ->map(fn ($e) => ['type' => 'Client entity', 'name' => $e->name, 'detail' => "of {$e->client->name}"]))
            ->concat(Contact::with('client:id,name')->where('name', 'like', $like)->limit(5)->get()
                ->map(fn ($c) => ['type' => 'Contact', 'name' => $c->name, 'detail' => "at {$c->client?->name}"]))
            ->concat(Party::withCount('matters')->where('name', 'like', $like)->limit(5)->get()
                ->map(fn ($p) => [
                    'type' => 'Party',
                    'name' => $p->name,
                    'detail' => "{$p->matters_count} matter(s) — check the capacity they act in",
                ]))
            ->values();

        return response()->json(['matches' => $matches]);
    }
}
