<?php

return [
    'numbering' => [
        'prefix' => env('SALES_ORDER_PREFIX', 'SO'),
        'sequence_padding' => 5,
    ],
    'delivery_numbering' => [
        'prefix' => env('SALES_DELIVERY_PREFIX', 'SD'),
        'sequence_padding' => 5,
    ],
    'reservation' => [
        'tolerance' => 0.0005,
    ],
];


