<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Disbursement;
use App\Models\TimeEntry;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\MatterRepository;
use App\Repositories\RenewalRepository;
use App\Repositories\TaskRepository;

class DashboardService
{
    public function __construct(
        private MatterRepository $matters,
        private ClientRepository $clients,
        private TaskRepository $tasks,
        private RenewalRepository $renewals,
    ) {
    }

    /** Everything the dashboard page shows. */
    public function overview(User $user): array
    {
        return [
            'stats' => [
                'activeMatters' => $this->matters->activeCount(),
                'clients' => $this->clients->count(),
                'openTasks' => $this->tasks->openCount(),
                'overdueTasks' => $this->tasks->overdueCount(),
                'renewalsDue90' => $this->renewals->openDueWithinCount(90),
                'myWipBase' => $this->wipInBaseFor($user),
                'baseCurrency' => config('billing.base_currency'),
            ],
            'mattersByType' => $this->matters->activeCountsByType(),
            'upcomingTasks' => $this->tasks->upcoming(8),
            'upcomingRenewals' => $this->renewals->upcoming(8),
            'recentMatters' => $this->matters->recent(6),
        ];
    }

    /**
     * Unbilled WIP on the user's portfolio (matters they're responsible
     * for), from the base values stored at capture.
     */
    private function wipInBaseFor(User $user): float
    {
        $mine = fn ($q) => $q->whereHas(
            'matter', fn ($m) => $m->where('responsible_user_id', $user->id)
        );

        return round(
            \App\Support\MoneyMinor::fromMinor(
                (int) TimeEntry::billable()->tap($mine)->sum('base_amount')
                + (int) Disbursement::billable()->tap($mine)->sum('base_amount')
                + (int) Charge::billable()->tap($mine)->sum('base_amount')
            ),
            2
        );
    }
}
