<?php

namespace App\Console\Commands;

use App\Enums\RenewalStatus;
use App\Enums\TaskStatus;
use App\Mail\ReminderDigestMail;
use App\Models\MatterTask;
use App\Models\Renewal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The morning docket email: each user gets their open tasks that are
 * overdue or due soon (assigned to them, or unassigned on matters they
 * are responsible for) plus renewals coming due on their matters.
 */
class SendReminderDigests extends Command
{
    protected $signature = 'reminders:digest';

    protected $description = 'Email each user their due tasks and upcoming renewals';

    public function handle(): int
    {
        $taskHorizon = now()->addDays((int) config('mailroom.digest.task_days', 7));
        $renewalHorizon = now()->addDays((int) config('mailroom.digest.renewal_days', 30));
        $sent = 0;

        foreach (User::all() as $user) {
            $tasks = MatterTask::query()
                ->with('matter:id,reference')
                ->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                ->where('due_date', '<=', $taskHorizon)
                ->where(fn ($q) => $q
                    ->where('assigned_to', $user->id)
                    ->orWhere(fn ($q2) => $q2
                        ->whereNull('assigned_to')
                        ->whereHas('matter', fn ($m) => $m->where('responsible_user_id', $user->id))))
                ->orderBy('due_date')
                ->get();

            $renewals = Renewal::query()
                ->with('matter:id,reference,country_code,responsible_user_id')
                ->whereIn('status', [RenewalStatus::Upcoming, RenewalStatus::ReminderSent])
                ->whereBetween('due_date', [now()->subDay(), $renewalHorizon])
                ->whereHas('matter', fn ($m) => $m->where('responsible_user_id', $user->id))
                ->orderBy('due_date')
                ->get();

            if ($tasks->isEmpty() && $renewals->isEmpty()) {
                continue;
            }

            Mail::to($user->email, $user->name)->send(new ReminderDigestMail($user, $tasks, $renewals));
            $sent++;
        }

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
