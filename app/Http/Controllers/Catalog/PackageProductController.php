<?php

namespace App\Http\Controllers\Catalog;

class PackageProductController extends BaseProductController
{
    protected string $type = 'package';

    protected function viewBase(): string
    {
        return 'Catalog/Package';
    }

    protected function routeBase(): string
    {
        return 'catalog.packages';
    }
}


