<?php

namespace App\Domain\Store\DTOs;
final class CancelInvoiceDTO
{
    public function __construct(
       public readonly string $invoiceId,
        public readonly string $storeId,
        public readonly string $cancelledBy,
        public readonly string $reason,
    ) {

    }
    public static function fromArray(array $data, string $invoiceId, string $storeId, string $cancelledBy): self
    {
        return new self(
            invoiceId:   $invoiceId,
            storeId:     $storeId,
            cancelledBy: $cancelledBy,
            reason:      $data['reason'] ?? null,
        );
    }
}