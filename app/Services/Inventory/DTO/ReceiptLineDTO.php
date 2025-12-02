<?php

namespace App\Services\Inventory\DTO;

readonly class ReceiptLineDTO
{
    public function __construct(
        public int $productVariantId,
        public int $uomId,
        public float $quantity,
        public float $unitCost,
        public ?int $lotId = null,
        public ?int $serialId = null,
    ) {
    }
}


