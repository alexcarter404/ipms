<?php

namespace App\Http\Integrations\OfficeExchange\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** Push an outbound filing/response/payment package to the office. */
class SubmitSubmissionRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private array $payload)
    {
    }

    public function resolveEndpoint(): string
    {
        return '/submissions';
    }

    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
