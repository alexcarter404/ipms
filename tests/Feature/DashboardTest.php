<?php

namespace Tests\Feature;

use App\Models\Matter;
use App\Models\MatterTask;
use App\Models\Renewal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_stats(): void
    {
        $user = User::factory()->create();

        Matter::factory()->count(2)->create();
        Matter::factory()->create(['status' => 'abandoned']);
        MatterTask::factory()->overdue()->create();
        Renewal::factory()->create(['due_date' => now()->addDays(30)]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.activeMatters', 4) // 2 + task's matter + renewal's matter
                ->where('stats.overdueTasks', 1)
                ->where('stats.renewalsDue90', 1)
                ->has('upcomingTasks')
                ->has('upcomingRenewals')
                ->has('recentMatters'));
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }
}
