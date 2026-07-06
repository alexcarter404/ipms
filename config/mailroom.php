<?php

return [

    /*
    | Directory (on the local storage disk) the mail ingest watches:
    | one JSON file per email or batch, dropped by the SMTP gateway.
    | Files are archived alongside after ingestion. An IMAP/Graph
    | driver can feed the same ingest pipeline later.
    */
    'inbox_path' => 'mail-inbox',

    /*
    | How far ahead the daily reminder digest looks.
    */
    'digest' => [
        'task_days' => 7,
        'renewal_days' => 30,
    ],

];
