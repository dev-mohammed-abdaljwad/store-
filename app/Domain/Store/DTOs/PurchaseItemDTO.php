<?php

namespace App\Domain\Store\DTOs;

final class PurchaseItemDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly float $orderedQuantity,
        public readonly float $receivedQuantity,
        public readonly float $unitPrice,
    ) {}
}
