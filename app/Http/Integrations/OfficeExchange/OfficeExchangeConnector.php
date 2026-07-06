<?php

namespace App\Http\Integrations\OfficeExchange;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;

/**
 * Saloon connector for REST-based office exchanges (EPO OPS, USPTO
 * ODP, WIPO ePCT, …). Base URL and token come from the office's
 * config; every office API speaks through the same request classes so
 * the ingestion pipeline sees one consistent shape.
 */
class OfficeExchangeConnector extends Connector
{
    public function __construct(
        private string $baseUrl,
        private ?string $token = null,
    ) {
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function defaultAuth(): ?TokenAuthenticator
    {
        return $this->token ? new TokenAuthenticator($this->token) : null;
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }
}
