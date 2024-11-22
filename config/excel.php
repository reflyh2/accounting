<?php

use Maatwebsite\Excel\Excel;

return [
    'exports' => [
        'pdf' => [
            'enabled' => true,
            'pdf_generator' => 'mpdf',
            'options' => [
                'default_font_size' => 10,
                'default_font' => 'sans-serif',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ],
        ],
    ],
];