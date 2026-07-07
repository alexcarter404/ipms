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

    public function submit(array $payload): array
    {
        $disk = Storage::disk('local');
        $outbox = config('integrations.outbox_path');
        $disk->makeDirectory($outbox);
        $disk->put(
            sprintf('%s/%s-submission-%s.json', $outbox, $this->office, $payload['submission_id']),
            json_encode($payload, JSON_PRETTY_PRINT)
        );

        // File exchanges acknowledge later via an inbound 'receipt'.
        return ['acknowledged' => false, 'external_ref' => null, 'receipt' => null];
    }

    /**
     * The register fixture: one JSON map per office at
     * ipo-register/<office>.json, keyed by normalised application
     * number (the SFTP-era "register extract" pattern).
     */
    public function lookup(string $applicationNo): ?array
    {
        $disk = Storage::disk('local');
        $path = config('integrations.register_path', 'ipo-register')."/{$this->office}.json";

        if (! $disk->exists($path)) {
            return null;
        }

        $register = json_decode($disk->get($path), true) ?? [];
        $normalise = fn (string $n) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $n));

        foreach ($register as $number => $record) {
            if ($normalise((string) $number) === $normalise($applicationNo)) {
                return $record;
            }
        }

        return null;
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
