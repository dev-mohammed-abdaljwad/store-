<?php

namespace App\Domain\Store\DTOs;

final class CreateSalesReturnDTO
{
    /** @param \App\Domain\Store\DTOs\ReturnItemDTO[] $items */
    public function __construct(
        public readonly int $storeId,
        public readonly int $customerId,
        public readonly ?int $salesInvoiceId,
        public readonly array $items,
        public readonly float $refundAmount,
        public readonly int $createdBy,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data, int $storeId, int $createdBy): self
    {
        $items = array_map(
            fn(array $i) => new \App\Domain\Store\DTOs\ReturnItemDTO(
                variantId: (int) $i['variant_id'],
                quantity: (float) $i['quantity'],
                unitPrice: (float) $i['unit_price'],
            ),
            $data['items'] ?? []
        );

        return new self(
            storeId: $storeId,
            customerId: (int) $data['customer_id'],
            salesInvoiceId: isset($data['sales_invoice_id']) ? (int) $data['sales_invoice_id'] : null,
            items: $items,
            refundAmount: (float) ($data['refund_amount'] ?? 0),
            createdBy: $createdBy,
            notes: $data['notes'] ?? null,
        );
    }
}
