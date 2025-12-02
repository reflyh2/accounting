<?php

return [
    'maker_checker' => [
        'enforce' => env('PURCHASING_ENFORCE_MAKER_CHECKER', false),
    ],
    'numbering' => [
        'prefix' => env('PURCHASE_ORDER_PREFIX', 'PO'),
        'sequence_padding' => 5,
    ],
];


