<?php

namespace App\Console\Commands;

use App\Mail\ScheduledReportMail;
use App\Models\Report;
use App\Services\ReportRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Deliver saved reports on their schedule: daily ones every run,
 * weekly ones on Mondays.
 */
class SendScheduledReports extends Command
{
    protected $signature = 'reports:send';

    protected $description = 'Email scheduled reports as CSV to their creators';

    public function handle(ReportRunner $runner): int
    {
        $due = Report::whereNotNull('schedule')
            ->get()
            ->filter(fn (Report $report) => $report->schedule === 'daily' || now()->isMonday());
        $sent = 0;

        foreach ($due as $report) {
            $result = $runner->run($report->type, $report->filters ?? []);

            Mail::to($report->creator->email, $report->creator->name)
                ->send(new ScheduledReportMail($report, $runner->toCsv($result), count($result['rows'])));

            $report->update(['last_run_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} report(s).");

        return self::SUCCESS;
    }
}
