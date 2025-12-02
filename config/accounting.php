<?php

return [
    'events' => [
        'driver' => env('ACCOUNTING_EVENTS_DRIVER', 'log'),
        'log_channel' => env('ACCOUNTING_EVENTS_LOG_CHANNEL', 'stack'),
    ],
];


