<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\Matter;
use App\Services\Mailroom\IngestInboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailroomController extends Controller
{
    public function index(): Response
    {
        $emails = Communication::query()
            ->where('direction', 'inbound')
            ->with('matter:id,reference,title')
            ->orderByRaw('case when matter_id is null then 0 else 1 end')
            ->latest('received_at')
            ->limit(100)
            ->get()
            ->map(fn (Communication $email) => [
                'id' => $email->id,
                'from_name' => $email->from_name,
                'from_email' => $email->from_email,
                'subject' => $email->subject,
                'body' => $email->body,
                'received_at' => $email->received_at?->toDateTimeString(),
                'matter' => $email->matter?->only('id', 'reference', 'title'),
                'attachments' => collect($email->attachments ?? [])->map(fn ($a) => [
                    'name' => $a['name'] ?? 'attachment',
                    'document_id' => $a['document_id'] ?? null,
                ])->all(),
            ]);

        return Inertia::render('Mailroom/Index', [
            'emails' => $emails,
            'unmatchedCount' => $emails->whereNull('matter')->count(),
            'matterOptions' => Matter::orderBy('reference')
                ->get(['id', 'reference', 'title'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => "{$m->reference} — {$m->title}"]),
        ]);
    }

    public function ingest(IngestInboundMail $ingest): RedirectResponse
    {
        $stats = $ingest->ingestFromInbox();

        return back()->with('success',
            "Checked the mailbox — {$stats['ingested']} new email(s), {$stats['matched']} matched.");
    }

    public function assign(Request $request, Communication $communication, IngestInboundMail $ingest): RedirectResponse
    {
        $data = $request->validate(['matter_id' => ['required', 'exists:matters,id']]);

        if ($communication->direction !== 'inbound' || $communication->matter_id) {
            return back()->with('error', 'Only unmatched inbound emails can be assigned.');
        }

        $matter = Matter::findOrFail($data['matter_id']);
        $ingest->assign($communication, $matter);

        return back()->with('success', "Email filed on {$matter->reference} — attachments added to its documents.");
    }
}
