<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\Matter;

class DocumentRepository
{
    /** Current versions on the matter's docket, newest first. */
    public function forMatter(Matter $matter): array
    {
        return Document::query()
            ->where('matter_id', $matter->id)
            ->current()
            ->with('uploader:id,name')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category->value,
                'category_label' => $document->category->label(),
                'source' => $document->source,
                'filename' => $document->filename,
                'size' => $this->humanSize($document->size),
                'version' => $document->version,
                'uploaded_by' => $document->uploader?->name ?? 'System',
                'created_at' => $document->created_at->toDateTimeString(),
            ])
            ->all();
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
