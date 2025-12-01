<?php

namespace App\Http\Controllers\Catalog;

class GoodsProductController extends BaseProductController
{
    protected string $type = 'goods';

    protected function viewBase(): string
    {
        return 'Catalog/Goods';
    }

    protected function routeBase(): string
    {
        return 'catalog.goods';
    }
}


