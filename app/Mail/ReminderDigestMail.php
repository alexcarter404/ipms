<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** The morning docket: a user's due tasks and renewals. */
class ReminderDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $tasks,
        public Collection $renewals,
    ) {
    }

    public function envelope(): Envelope
    {
        $overdue = $this->tasks->filter(fn ($task) => $task->due_date->isPast())->count();

        return new Envelope(subject: sprintf(
            'Your IPMS docket: %d task(s)%s, %d renewal(s) due',
            $this->tasks->count(),
            $overdue ? " ({$overdue} overdue)" : '',
            $this->renewals->count(),
        ));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reminder-digest', with: [
            'user' => $this->user,
            'tasks' => $this->tasks,
            'renewals' => $this->renewals,
        ]);
    }
}
