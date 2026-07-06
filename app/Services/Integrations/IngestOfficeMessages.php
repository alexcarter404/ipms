<?php

namespace App\Services\Integrations;

use App\Actions\Integrations\ProcessOfficeMessage;
use App\Enums\OfficeEventType;
use App\Enums\OfficeMessageStatus;
use App\Http\Integrations\OfficeExchange\OfficeExchangeConnector;
use App\Models\OfficeMessage;
use Illuminate\Support\Carbon;

/**
 * Idempotent ingestion: store each inbound message once (per office +
 * external id), match it to a matter, and — when auto-processing is on
 * and the match is unambiguous — run the automation pipeline.
 */
class IngestOfficeMessages
{
    public function __construct(
        private MessageMatcher $matcher,
        private ProcessOfficeMessage $processor,
    ) {
    }

    /** @return array{ingested: int, processed: int, review: int} */
    public function ingest(string $office, array $messages): array
    {
        $stats = ['ingested' => 0, 'processed' => 0, 'review' => 0];

        foreach ($messages as $data) {
            if (empty($data['external_id']) || empty($data['event_type'])) {
                continue;
            }

            $exists = OfficeMessage::where('office', $office)
                ->where('external_id', $data['external_id'])
                ->exists();

            if ($exists) {
                continue; // already ingested — dedupe on the office's id
            }

            $message = OfficeMessage::create([
                'office' => $office,
                'external_id' => $data['external_id'],
                'event_type' => $data['event_type'],
                'application_no' => $data['application_no'] ?? null,
                'registration_no' => $data['registration_no'] ?? null,
                'event_date' => $data['event_date'] ?? null,
                'summary' => $data['summary'] ?? null,
                'payload' => $data['payload'] ?? null,
                'received_at' => isset($data['received_at']) ? Carbon::parse($data['received_at']) : now(),
                'status' => OfficeMessageStatus::NeedsReview,
            ]);
            $stats['ingested']++;

            if ($message->event_type === OfficeEventType::Receipt) {
                // Correlated by submission id inside the processor.
                $message->update(['status' => OfficeMessageStatus::Matched]);
            } else {
                $matter = $this->matcher->match($message);

                if (! $matter) {
                    $stats['review']++;

                    continue;
                }

                $message->update(['matter_id' => $matter->id, 'status' => OfficeMessageStatus::Matched]);
            }

            if (config('integrations.auto_process')) {
                try {
                    $this->processor->handle($message);
                    $stats['processed']++;
                } catch (\Throwable $e) {
                    $message->update([
                        'status' => OfficeMessageStatus::NeedsReview,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['review']++;
                }
            }
        }

        return $stats;
    }

    /** Poll every configured office connector and ingest what it returns. */
    public function pollAll(): array
    {
        $totals = ['ingested' => 0, 'processed' => 0, 'review' => 0];

        foreach (array_keys(config('integrations.offices')) as $office) {
            $stats = $this->ingest($office, $this->connector($office)->fetch());
            foreach ($stats as $key => $value) {
                $totals[$key] += $value;
            }
        }

        return $totals;
    }

    public function connector(string $office): IpoConnector
    {
        $config = config("integrations.offices.{$office}", []);

        return match ($config['driver'] ?? 'filedrop') {
            'api' => new ApiConnector($office, new OfficeExchangeConnector(
                $config['base_url'] ?? '',
                $config['token'] ?? null,
            )),
            default => new FileDropConnector($office),
        };
    }
}
