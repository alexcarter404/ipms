<?php

namespace App\Http\Controllers;

use App\Actions\Documents\GenerateDocument;
use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentCategory;
use App\Exceptions\DomainActionException;
use App\Models\CommTemplate;
use App\Models\Document;
use App\Models\Matter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request, Matter $matter, StoreDocument $action): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:25600'], // 25 MB
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
        ]);

        $document = $action->fromUpload($matter, $request->user(), $request->file('file'), [
            'title' => $data['title'] ?? '',
            'category' => DocumentCategory::from($data['category']),
        ]);

        return back()->with('success', "Document “{$document->title}” filed on {$matter->reference}.");
    }

    public function generate(Request $request, Matter $matter, GenerateDocument $action): RedirectResponse
    {
        $data = $request->validate([
            'comm_template_id' => ['required', 'exists:comm_templates,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $document = $action->handle(
            $matter,
            CommTemplate::findOrFail($data['comm_template_id']),
            $request->user(),
            $data['title'] ?? null
        );

        return back()->with('success', "Generated “{$document->title}” as a PDF on the docket.");
    }

    public function replace(Request $request, Document $document, StoreDocument $action): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:25600']]);

        try {
            $replacement = $action->replaceWith($document, $request->user(), $request->file('file'));
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Document “{$replacement->title}” replaced — now v{$replacement->version}.");
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
        ]);

        $document->update($data);

        return back()->with('success', 'Document details updated.');
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->filename);
    }

    public function destroy(Document $document): RedirectResponse
    {
        // Keep the bytes of superseded versions; only this record's file goes
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }
}
