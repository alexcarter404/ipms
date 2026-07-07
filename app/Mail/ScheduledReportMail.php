<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A scheduled report delivered as CSV to its creator. */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Report $report, public string $csv, public int $rowCount) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "IPMS report: {$this->report->name} ({$this->rowCount} rows)");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.scheduled-report', with: [
            'report' => $this->report,
            'rowCount' => $this->rowCount,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csv, "{$this->report->type}-report.csv")
                ->withMime('text/csv'),
        ];
    }
}
