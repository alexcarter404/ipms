<?php

namespace App\Services\Mailroom;

use App\Actions\Documents\StoreDocument;
use App\Models\Communication;
use App\Models\Matter;
use Illuminate\Support\Facades\Storage;

/**
 * The firm's docketing mailbox, captured onto matters. Emails arrive
 * as JSON drops in storage (the SMTP-gateway pattern — an IMAP/Graph
 * driver can feed the same ingest later): matched mail lands on the
 * matter as an inbound communication with its attachments filed as
 * documents; anything unmatched waits in the Mailroom for a human.
 */
class IngestInboundMail
{
    public function __construct(private StoreDocument $documents)
    {
    }

    /** @return array{ingested: int, matched: int} */
    public function ingestFromInbox(): array
    {
        $disk = Storage::disk('local');
        $path = config('mailroom.inbox_path', 'mail-inbox');
        $stats = ['ingested' => 0, 'matched' => 0];

        foreach ($disk->files($path) as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            $emails = json_decode($disk->get($file), true) ?? [];
            // A drop file may hold one email or a batch
            $emails = array_is_list($emails) ? $emails : [$emails];

            foreach ($emails as $email) {
                $result = $this->ingest($email);
                $stats['ingested'] += $result ? 1 : 0;
                $stats['matched'] += $result?->matter_id ? 1 : 0;
            }

            $disk->move($file, str_replace($path, "{$path}/archive", $file));
        }

        return $stats;
    }

    /** Idempotent on the mailbox message id. */
    public function ingest(array $email): ?Communication
    {
        $externalId = $email['message_id'] ?? null;

        if ($externalId && Communication::where('external_id', $externalId)->exists()) {
            return null;
        }

        $matter = $this->match($email);

        $communication = Communication::create([
            'matter_id' => $matter?->id,
            'channel' => 'email',
            'direction' => 'inbound',
            'from_name' => $email['from_name'] ?? null,
            'from_email' => $email['from'] ?? null,
            'subject' => $email['subject'] ?? '(no subject)',
            'body' => $email['body'] ?? '',
            'status' => 'received',
            'received_at' => $email['received_at'] ?? now(),
            'external_id' => $externalId,
            'attachments' => $email['attachments'] ?? [],
        ]);

        if ($matter) {
            $this->fileAttachments($communication);
        }

        return $communication;
    }

    /** Attach an unmatched email to a matter and file what it carried. */
    public function assign(Communication $communication, Matter $matter): Communication
    {
        $communication->update(['matter_id' => $matter->id]);

        return $this->fileAttachments($communication->fresh());
    }

    /**
     * Match by matter reference in the subject, then by any official
     * number found in the subject or body (normalised, like the office
     * exchange matcher) — only an unambiguous hit counts.
     */
    private function match(array $email): ?Matter
    {
        $subject = $email['subject'] ?? '';
        $text = $subject.' '.($email['body'] ?? '');

        if (preg_match_all('/\b([A-Z]{1,3}-\d{4}-\d{4})\b/', $subject, $m)) {
            $matters = Matter::whereIn('reference', array_unique($m[1]))->get();
            if ($matters->count() === 1) {
                return $matters->first();
            }
        }

        // Normalised official numbers: strip everything but alphanumerics
        $normalise = fn (string $n) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $n));
        $haystack = $normalise($text);

        $candidates = Matter::query()
            ->where(fn ($q) => $q->whereNotNull('application_no')->orWhereNotNull('registration_no'))
            ->get()
            ->filter(function (Matter $matter) use ($haystack, $normalise) {
                foreach ([$matter->application_no, $matter->registration_no] as $number) {
                    if ($number && str_contains($haystack, $normalise($number))) {
                        return true;
                    }
                }

                return false;
            });

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /** Pending base64 attachments become documents on the matter. */
    private function fileAttachments(Communication $communication): Communication
    {
        $filed = [];

        foreach ($communication->attachments ?? [] as $attachment) {
            if (isset($attachment['document_id'])) {
                $filed[] = $attachment; // already on the docket
                continue;
            }

            $content = base64_decode($attachment['content_base64'] ?? '', true);
            if (! $content || empty($attachment['name'])) {
                continue;
            }

            $document = $this->documents->fromContent($communication->matter, $attachment['name'], $content, [
                'title' => pathinfo($attachment['name'], PATHINFO_FILENAME),
                'category' => \App\Enums\DocumentCategory::Correspondence,
                'source' => 'email',
                'mime' => $attachment['mime'] ?? null,
                'linked_type' => Communication::class,
                'linked_id' => $communication->id,
            ]);
            $filed[] = ['name' => $attachment['name'], 'document_id' => $document->id];
        }

        $communication->update(['attachments' => $filed]);

        return $communication;
    }
}
