<?php

return [
    'numbering' => [
        'prefix' => env('SALES_ORDER_PREFIX', 'SO'),
        'sequence_padding' => 5,
    ],
    'reservation' => [
        'tolerance' => 0.0005,
    ],
];


