<?php

namespace App\Actions\Documents;

use App\Enums\DocumentCategory;
use App\Models\CommTemplate;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use App\Services\TemplateRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Turn a communication template into a PDF on the docket: merge fields
 * resolved against the matter, rendered on the firm letterhead view,
 * filed as a generated document.
 */
class GenerateDocument
{
    public function __construct(private TemplateRenderer $renderer, private StoreDocument $store)
    {
    }

    public function handle(Matter $matter, CommTemplate $template, User $user, ?string $title = null): Document
    {
        $rendered = $this->renderer->render($template, $matter);
        $title = $title ?: ($rendered['subject'] ?: $template->name);

        $pdf = Pdf::loadView('documents.letter', [
            'matter' => $matter,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ])->output();

        $document = $this->store->fromContent(
            $matter,
            Str::slug($title).'.pdf',
            $pdf,
            [
                'title' => $title,
                'category' => DocumentCategory::Generated,
                'source' => 'generated',
                'mime' => 'application/pdf',
                'uploaded_by' => $user->id,
            ]
        );

        return $document;
    }
}
