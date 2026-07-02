<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\Renewal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $mattersByType = Matter::active()
            ->selectRaw('matter_type, count(*) as total')
            ->groupBy('matter_type')
            ->pluck('total', 'matter_type');

        return Inertia::render('Dashboard', [
            'stats' => [
                'activeMatters' => Matter::active()->count(),
                'clients' => Client::count(),
                'openTasks' => MatterTask::open()->count(),
                'overdueTasks' => MatterTask::overdue()->count(),
                'renewalsDue90' => Renewal::open()->dueWithin(90)->count(),
            ],
            'mattersByType' => $mattersByType,
            'upcomingTasks' => MatterTask::open()
                ->with(['matter:id,reference,title', 'assignee:id,name'])
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'upcomingRenewals' => Renewal::open()
                ->with('matter:id,reference,title,matter_type,country_code')
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'recentMatters' => Matter::with('client:id,name')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }
}
