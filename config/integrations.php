<?php

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
    'offices' => [
        'epo' => [
            'name' => 'European Patent Office',
            'driver' => env('EPO_DRIVER', 'filedrop'),
            'base_url' => env('EPO_API_URL'),
            'token' => env('EPO_API_TOKEN'),
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
    | Process matched messages immediately on ingestion. When false,
    | everything waits in the inbox for a human to hit Process.
    */
    'auto_process' => env('INTEGRATIONS_AUTO_PROCESS', true),

];
