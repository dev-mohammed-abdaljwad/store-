<?php

namespace App\Domain\Store\DTOs;

final class PurchaseItemDTO
{
    public function __construct(
        public readonly int $variantId,
        public readonly float $orderedQuantity,
        public readonly float $receivedQuantity,
        public readonly float $unitPrice,
    ) {}
}
