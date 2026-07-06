<?php

namespace Tests\Feature;

use App\Mail\CommunicationMail;
use App\Mail\ReminderDigestMail;
use App\Models\Client;
use App\Models\Communication;
use App\Models\Matter;
use App\Models\User;
use App\Services\Mailroom\IngestInboundMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailroomTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'reference' => 'P-2026-0001',
            'application_no' => 'EP24123456.7',
        ]);
    }

    public function test_mail_with_a_reference_in_the_subject_files_itself_with_attachments(): void
    {
        $email = app(IngestInboundMail::class)->ingest([
            'message_id' => 'MSG-1',
            'from' => 'agent@example.com',
            'subject' => 'Re: P-2026-0001 — report enclosed',
            'body' => 'Please see the attached report.',
            'attachments' => [[
                'name' => 'report.pdf', 'mime' => 'application/pdf',
                'content_base64' => base64_encode('%PDF-1.4 report'),
            ]],
        ]);

        $this->assertSame($this->matter->id, $email->matter_id);
        $this->assertSame('inbound', $email->direction);
        $this->assertSame('received', $email->status);

        $document = $this->matter->documents()->first();
        $this->assertNotNull($document);
        $this->assertSame('report', $document->title);
        $this->assertSame('email', $document->source);
        $this->assertSame('correspondence', $document->category->value);
        $this->assertSame($document->id, $email->fresh()->attachments[0]['document_id']);
    }

    public function test_mail_matches_by_normalised_official_number_in_the_body(): void
    {
        $email = app(IngestInboundMail::class)->ingest([
            'message_id' => 'MSG-2',
            'from' => 'epo@example.com',
            'subject' => 'Communication pursuant to Article 94(3)',
            'body' => 'Application EP 24 123 456.7 — observations due within four months.',
        ]);

        $this->assertSame($this->matter->id, $email->matter_id);
    }

    public function test_unmatched_mail_waits_in_the_mailroom_and_can_be_assigned(): void
    {
        $email = app(IngestInboundMail::class)->ingest([
            'message_id' => 'MSG-3',
            'from' => 'someone@example.com',
            'subject' => 'General enquiry',
            'body' => 'No reference here at all.',
            'attachments' => [[
                'name' => 'note.txt', 'content_base64' => base64_encode('hello'),
            ]],
        ]);

        $this->assertNull($email->matter_id);
        // Attachments stay pending until the email has a home
        $this->assertArrayNotHasKey('document_id', $email->attachments[0]);
        $this->assertCount(0, $this->matter->documents()->get());

        $this->actingAs($this->user)
            ->patch(route('mailroom.assign', $email), ['matter_id' => $this->matter->id])
            ->assertSessionHas('success');

        $this->assertSame($this->matter->id, $email->fresh()->matter_id);
        $this->assertSame('note', $this->matter->documents()->first()->title);
    }

    public function test_ingestion_is_idempotent_on_the_message_id(): void
    {
        $payload = [
            'message_id' => 'MSG-DUP',
            'from' => 'a@example.com',
            'subject' => 'P-2026-0001 status',
            'body' => 'Ping',
        ];

        app(IngestInboundMail::class)->ingest($payload);
        $this->assertNull(app(IngestInboundMail::class)->ingest($payload));
        $this->assertSame(1, Communication::where('external_id', 'MSG-DUP')->count());
    }

    public function test_the_inbox_drop_is_ingested_and_archived(): void
    {
        Storage::disk('local')->put('mail-inbox/batch-1.json', json_encode([[
            'message_id' => 'MSG-DROP-1',
            'from' => 'drop@example.com',
            'subject' => 'P-2026-0001 — annuity receipt',
            'body' => 'Receipt attached.',
        ]]));

        $stats = app(IngestInboundMail::class)->ingestFromInbox();

        $this->assertSame(['ingested' => 1, 'matched' => 1], $stats);
        $this->assertCount(0, array_filter(
            Storage::disk('local')->files('mail-inbox'),
            fn ($f) => str_ends_with($f, '.json')
        ));
        $this->assertCount(1, Storage::disk('local')->files('mail-inbox/archive'));
    }

    public function test_marking_an_email_comm_sent_delivers_it(): void
    {
        Mail::fake();

        $comm = $this->matter->communications()->create([
            'channel' => 'email', 'recipient_name' => 'Sam Client',
            'recipient_email' => 'sam@client.example', 'subject' => 'Report',
            'body' => 'Please find our report.', 'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->post(route('communications.send', $comm))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Email sent to sam@client.example'));

        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail) => $mail->hasTo('sam@client.example')
            && $mail->communication->is($comm));
        $this->assertSame('sent', $comm->fresh()->status);
    }

    public function test_letters_are_marked_sent_without_email_delivery(): void
    {
        Mail::fake();

        $comm = $this->matter->communications()->create([
            'channel' => 'letter', 'recipient_name' => 'Sam Client',
            'subject' => 'Letter', 'body' => 'By post.', 'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->post(route('communications.send', $comm))
            ->assertSessionHas('success', 'Communication marked as sent.');

        Mail::assertNothingSent();
        $this->assertSame('sent', $comm->fresh()->status);
    }

    public function test_the_reminder_digest_emails_each_users_docket(): void
    {
        Mail::fake();

        $this->matter->update(['responsible_user_id' => $this->user->id]);
        $this->matter->tasks()->create([
            'title' => 'File response', 'due_date' => now()->subDay(),
            'status' => 'pending', 'priority' => 'high',
        ]);
        $this->matter->renewals()->create([
            'cycle' => 4, 'due_date' => now()->addDays(10), 'status' => 'upcoming',
        ]);

        $idle = User::factory()->create();

        Artisan::call('reminders:digest');

        Mail::assertSent(ReminderDigestMail::class, fn (ReminderDigestMail $mail) => $mail->hasTo($this->user->email)
            && $mail->tasks->count() === 1
            && $mail->renewals->count() === 1);
        Mail::assertNotSent(ReminderDigestMail::class, fn (ReminderDigestMail $mail) => $mail->hasTo($idle->email));
    }

    public function test_the_mailroom_page_lists_inbound_mail(): void
    {
        app(IngestInboundMail::class)->ingest([
            'message_id' => 'MSG-PAGE', 'from' => 'page@example.com',
            'subject' => 'Untraceable', 'body' => 'x',
        ]);

        $this->actingAs($this->user)
            ->get(route('mailroom.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('emails', 1)
                ->where('emails.0.subject', 'Untraceable')
                ->where('unmatchedCount', 1)
                ->has('matterOptions'));
    }
}
