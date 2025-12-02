<?php

namespace App\Services\Inventory\DTO;

use App\Models\InventoryTransaction;

readonly class InventoryTxnResult
{
    public function __construct(
        public InventoryTransaction $transaction,
        public float $totalQuantity,
        public float $totalValue,
    ) {
    }
}


