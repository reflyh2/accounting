<?php

namespace App\Http\Controllers\Catalog;

class RentalProductController extends BaseProductController
{
    protected string $type = 'rental';

    protected function viewBase(): string
    {
        return 'Catalog/Rental';
    }

    protected function routeBase(): string
    {
        return 'catalog.rental';
    }
}


