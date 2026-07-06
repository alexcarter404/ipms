<?php

namespace App\Actions\Documents;

use App\Enums\DocumentCategory;
use App\Exceptions\DomainActionException;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Files land on the docket three ways — user upload, office exchange
 * auto-filing, template generation — and all of them come through
 * here so storage layout and versioning stay in one place.
 */
class StoreDocument
{
    public function fromUpload(Matter $matter, User $user, UploadedFile $file, array $data): Document
    {
        $path = $file->storeAs($this->directory($matter), $this->hashedName($file->getClientOriginalName()), 'local');

        return $matter->documents()->create([
            'title' => $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'category' => $data['category'] ?? DocumentCategory::Other,
            'source' => 'upload',
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }

    /** A replacement supersedes the old version but keeps it downloadable. */
    public function replaceWith(Document $document, User $user, UploadedFile $file): Document
    {
        if ($document->revisions()->exists()) {
            throw new DomainActionException('This version has already been superseded — replace the latest one.');
        }

        $path = $file->storeAs(
            $this->directory($document->matter),
            $this->hashedName($file->getClientOriginalName()),
            'local'
        );

        return $document->matter->documents()->create([
            'linked_type' => $document->linked_type,
            'linked_id' => $document->linked_id,
            'title' => $document->title,
            'category' => $document->category,
            'source' => 'upload',
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'version' => $document->version + 1,
            'parent_id' => $document->id,
            'uploaded_by' => $user->id,
        ]);
    }

    /** Raw content from the office exchange or a generator. */
    public function fromContent(Matter $matter, string $filename, string $content, array $attributes = []): Document
    {
        $path = $this->directory($matter).'/'.$this->hashedName($filename);
        Storage::disk('local')->put($path, $content);

        return $matter->documents()->create($attributes + [
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'category' => DocumentCategory::Other,
            'source' => 'office',
            'filename' => $filename,
            'path' => $path,
            'mime' => null,
            'size' => strlen($content),
        ]);
    }

    private function directory(Matter $matter): string
    {
        return "documents/{$matter->id}";
    }

    /** Unique on disk; the original name lives on the record. */
    private function hashedName(string $original): string
    {
        $extension = pathinfo($original, PATHINFO_EXTENSION);

        return uniqid('doc_', true).($extension ? ".{$extension}" : '');
    }
}
