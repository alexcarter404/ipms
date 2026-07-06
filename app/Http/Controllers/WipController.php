<?php

namespace App\Http\Controllers;

use App\Models\ClientEntity;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Repositories\WipRepository;
use App\Support\Currencies;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WipController extends Controller
{
    public function index(
        Request $request,
        WipRepository $wip,
        ClientRepository $clients,
        UserRepository $users,
    ): Response {
        $filters = $request->only('client_id', 'user_id');
        $rows = $wip->entitySummary($filters);

        return Inertia::render('Billing/Wip', [
            'rows' => $rows,
            'baseCurrency' => Currencies::base(),
            'firmTotal' => round(array_sum(array_column($rows, 'base_total')), 2),
            'filters' => $filters,
            'clients' => $clients->options(),
            'users' => $users->options(),
        ]);
    }

    /** Drill-in: review, amend and bill one entity's WIP. */
    public function show(ClientEntity $entity, WipRepository $wip): Response
    {
        return Inertia::render('Billing/WipEntity', [
            'wip' => $wip->entityWip($entity),
            'baseCurrency' => Currencies::base(),
        ]);
    }
}
