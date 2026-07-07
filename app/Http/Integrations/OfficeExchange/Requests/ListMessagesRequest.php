<?php

namespace App\Http\Integrations\OfficeExchange\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Pull the office's pending inbound messages. */
class ListMessagesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private ?string $since = null) {}

    public function resolveEndpoint(): string
    {
        return '/messages';
    }

    protected function defaultQuery(): array
    {
        return array_filter(['since' => $this->since]);
    }
}
