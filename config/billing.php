<?php

return [

    /*
    | The firm's home currency. Exchange rates are stored as
    | 1 base unit = X units of the foreign currency.
    */
    'base_currency' => env('BILLING_BASE_CURRENCY', 'GBP'),

    /*
    | Currencies the firm can quote, charge and bill in. Client entities
    | pick their billing currency from this list.
    */
    'currencies' => ['GBP', 'EUR', 'USD', 'JPY', 'CNY', 'CHF', 'AUD', 'CAD'],

    /*
    | Default time-recording increment (minutes). Billing agreements can
    | override this per matter (e.g. 15-minute blocks).
    */
    'default_increment_minutes' => 6,

    /*
    | Days until an issued invoice falls due.
    */
    'payment_terms_days' => 30,

    /*
    | Exchange-rate provider endpoint (ECB-backed, no API key). The
    | billing:sync-rates command fetches daily rates from here; rates can
    | also be maintained by hand in Billing Settings.
    */
    'rates_url' => env('BILLING_RATES_URL', 'https://api.frankfurter.dev/v1/latest'),

    /*
    | Invoicing provider driver. 'internal' keeps the whole invoice
    | lifecycle in the IPMS; an external driver (e.g. Xero, Stripe) can
    | be registered later without touching the WIP layer.
    */
    'invoicing_provider' => env('BILLING_INVOICING_PROVIDER', 'internal'),

];
