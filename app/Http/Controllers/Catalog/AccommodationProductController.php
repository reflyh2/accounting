<?php

namespace App\Http\Controllers\Catalog;

class AccommodationProductController extends BaseProductController
{
    protected string $type = 'accommodation';

    protected function viewBase(): string
    {
        return 'Catalog/Accommodation';
    }

    protected function routeBase(): string
    {
        return 'catalog.accommodation';
    }
}


