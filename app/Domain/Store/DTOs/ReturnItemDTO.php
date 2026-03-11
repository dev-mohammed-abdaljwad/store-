<?php

namespace App\Domain\Store\DTOs;

final class ReturnItemDTO
{
    public function __construct(
        public readonly int $variantId,
        public readonly float $quantity,
        public readonly float $unitPrice,
    ) {}
}
