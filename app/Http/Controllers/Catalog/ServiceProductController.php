<?php

namespace App\Http\Controllers\Catalog;

class ServiceProductController extends BaseProductController
{
    protected string $type = 'service';

    protected function viewBase(): string
    {
        return 'Catalog/Service';
    }

    protected function routeBase(): string
    {
        return 'catalog.services';
    }
}


