<?php

namespace App\Http\Integrations\OfficeExchange\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Fetch one application's register record from the office API. */
class LookupRegisterRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private string $applicationNo) {}

    public function resolveEndpoint(): string
    {
        return '/register/'.rawurlencode($this->applicationNo);
    }
}
