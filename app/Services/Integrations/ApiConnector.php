<?php

namespace App\Services\Integrations;

use App\Http\Integrations\OfficeExchange\OfficeExchangeConnector;
use App\Http\Integrations\OfficeExchange\Requests\ListMessagesRequest;
use App\Http\Integrations\OfficeExchange\Requests\SubmitSubmissionRequest;
use App\Models\OfficeMessage;

/**
 * REST driver: pulls messages from an office's exchange API through
 * the Saloon connector. Configure per office:
 *
 *   'epo' => ['driver' => 'api', 'base_url' => env('EPO_API_URL'),
 *             'token' => env('EPO_API_TOKEN')]
 */
class ApiConnector implements IpoConnector
{
    public function __construct(
        private string $office,
        private OfficeExchangeConnector $connector,
    ) {
    }

    public function office(): string
    {
        return $this->office;
    }

    public function fetch(): array
    {
        $since = OfficeMessage::where('office', $this->office)
            ->max('received_at');

        $response = $this->connector->send(new ListMessagesRequest($since));

        return $response->json('messages', []);
    }

    public function submit(array $payload): array
    {
        $response = $this->connector->send(new SubmitSubmissionRequest($payload));
        $response->throw();

        // REST exchanges acknowledge synchronously with a receipt.
        return [
            'acknowledged' => true,
            'external_ref' => $response->json('receipt_id'),
            'receipt' => $response->json(),
        ];
    }
}
