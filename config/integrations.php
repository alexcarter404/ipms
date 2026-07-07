<?php

use App\Services\Integrations\Transformers\EpoOnlineFilingTransformer;

return [

    /*
    | IP offices the firm exchanges data with. Each office has a
    | connector driver; 'filedrop' reads JSON message files from the
    | inbox disk path below (the classic SFTP-exchange pattern). REST
    | drivers (EPO OPS, USPTO ODP, WIPO ePCT) can be registered here
    | later without touching the ingestion pipeline.
    */
    // Drivers: 'filedrop' (JSON batches in the inbox path) or 'api'
    // (REST exchange via the Saloon OfficeExchangeConnector — set
    // base_url + token).
    // 'transformer' points at the office's payload dialect — the class
    // that validates prerequisites and builds the wire format (e.g. the
    // EPO Online Filing request + fee sheet). Offices without one send
    // the canonical package as-is.
    'offices' => [
        'epo' => [
            'name' => 'European Patent Office',
            'driver' => env('EPO_DRIVER', 'filedrop'),
            'base_url' => env('EPO_API_URL'),
            'token' => env('EPO_API_TOKEN'),
            'transformer' => EpoOnlineFilingTransformer::class,
        ],
        'ukipo' => ['name' => 'UK IPO', 'driver' => 'filedrop'],
        'uspto' => [
            'name' => 'USPTO',
            'driver' => env('USPTO_DRIVER', 'filedrop'),
            'base_url' => env('USPTO_API_URL'),
            'token' => env('USPTO_API_TOKEN'),
        ],
        'wipo' => ['name' => 'WIPO', 'driver' => 'filedrop'],
        'euipo' => ['name' => 'EUIPO', 'driver' => 'filedrop'],
    ],

    /*
    | Directory (on the local storage disk) the file-drop connector
    | watches: one JSON file per batch, named <office>-*.json. Files are
    | archived alongside after ingestion.
    */
    'inbox_path' => 'ipo-inbox',

    /*
    | Where the file-drop driver writes outbound submissions for the
    | exchange to collect.
    */
    'outbox_path' => 'ipo-outbox',

    /*
    | Where register extracts live for the file-drop driver: one JSON
    | map per office (ipo-register/<office>.json) keyed by application
    | number. API drivers look the number up live instead.
    */
    'register_path' => 'ipo-register',

    /*
    | Which office speaks for which jurisdiction when importing or
    | reconciling by application number.
    */
    'office_by_country' => [
        'EP' => 'epo',
        'GB' => 'ukipo',
        'US' => 'uspto',
        'EU' => 'euipo',
        'WO' => 'wipo',
    ],

    /*
    | Process matched messages immediately on ingestion. When false,
    | everything waits in the inbox for a human to hit Process.
    */
    'auto_process' => env('INTEGRATIONS_AUTO_PROCESS', true),

];
