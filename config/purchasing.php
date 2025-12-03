<?php

return [
    'maker_checker' => [
        'enforce' => env('PURCHASING_ENFORCE_MAKER_CHECKER', false),
    ],
    'numbering' => [
        'prefix' => env('PURCHASE_ORDER_PREFIX', 'PO'),
        'sequence_padding' => 5,
    ],
    'goods_receipt_numbering' => [
        'prefix' => env('PURCHASE_GRN_PREFIX', 'GRN'),
        'sequence_padding' => 5,
    ],
    'purchase_return_numbering' => [
        'prefix' => env('PURCHASE_RETURN_PREFIX', 'PRN'),
        'sequence_padding' => 5,
    ],
    'return_reasons' => [
        'quality_issue' => 'Masalah Kualitas',
        'excess_quantity' => 'Kelebihan Kirim',
        'wrong_item' => 'Barang Tidak Sesuai',
        'other' => 'Lainnya',
    ],
];


