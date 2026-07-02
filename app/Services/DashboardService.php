<?php

namespace App\Services;

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
    public function overview(): array
    {
        return [
            'stats' => [
                'activeMatters' => $this->matters->activeCount(),
                'clients' => $this->clients->count(),
                'openTasks' => $this->tasks->openCount(),
                'overdueTasks' => $this->tasks->overdueCount(),
                'renewalsDue90' => $this->renewals->openDueWithinCount(90),
            ],
            'mattersByType' => $this->matters->activeCountsByType(),
            'upcomingTasks' => $this->tasks->upcoming(8),
            'upcomingRenewals' => $this->renewals->upcoming(8),
            'recentMatters' => $this->matters->recent(6),
        ];
    }
}
