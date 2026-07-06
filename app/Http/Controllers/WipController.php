<?php

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Repositories\WipRepository;
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

        return Inertia::render('Billing/Wip', [
            'groups' => $wip->summary($filters),
            'filters' => $filters,
            'clients' => $clients->options(),
            'users' => $users->options(),
        ]);
    }
}
