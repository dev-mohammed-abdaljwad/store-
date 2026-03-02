<?php

// ══════════════════════════════════════════════════════════════════
// CreateSalesInvoiceDTO.php
// ══════════════════════════════════════════════════════════════════

namespace App\Domain\Store\DTOs;

final class InvoiceItemDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly float  $quantity,
        public readonly float  $unitPrice,
    ) {}
}
