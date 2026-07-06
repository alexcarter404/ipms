<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Storage;

/**
 * Reads JSON batches dropped into the exchange inbox (one file per
 * batch, named <office>-*.json) and archives them after reading.
 */
class FileDropConnector implements IpoConnector
{
    public function __construct(private string $office)
    {
    }

    public function office(): string
    {
        return $this->office;
    }

    public function fetch(): array
    {
        $disk = Storage::disk('local');
        $inbox = config('integrations.inbox_path');
        $messages = [];

        foreach ($disk->files($inbox) as $file) {
            if (! str_starts_with(basename($file), "{$this->office}-") || ! str_ends_with($file, '.json')) {
                continue;
            }

            $batch = json_decode($disk->get($file), true);

            foreach (is_array($batch) ? $batch : [] as $message) {
                $messages[] = $message;
            }

            // Archive so a batch is only ingested once even if the
            // dedupe key were missing.
            $disk->makeDirectory("{$inbox}/archive");
            $disk->move($file, "{$inbox}/archive/".basename($file));
        }

        return $messages;
    }
}
