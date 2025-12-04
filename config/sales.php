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
    'sales_return_numbering' => [
        'prefix' => env('SALES_RETURN_PREFIX', 'SRN'),
        'sequence_padding' => 5,
    ],
    'return_reasons' => [
        'quality_issue' => 'Masalah Kualitas',
        'excess_quantity' => 'Kelebihan Kirim',
        'wrong_item' => 'Barang Tidak Sesuai',
        'customer_request' => 'Permintaan Customer',
        'other' => 'Lainnya',
    ],
];


