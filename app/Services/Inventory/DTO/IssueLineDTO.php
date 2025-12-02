<?php

namespace App\Services\Inventory\DTO;

readonly class IssueLineDTO
{
    public function __construct(
        public int $productVariantId,
        public int $uomId,
        public float $quantity,
        public ?int $lotId = null,
        public ?int $serialId = null,
    ) {
    }
}


