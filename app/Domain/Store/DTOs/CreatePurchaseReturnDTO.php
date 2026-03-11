<?php

namespace App\Domain\Store\DTOs;

final class CreatePurchaseReturnDTO
{
    /** @param \App\Domain\Store\DTOs\ReturnItemDTO[] $items */
    public function __construct(
        public readonly int $storeId,
        public readonly int $supplierId,
        public readonly ?int $purchaseInvoiceId,
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
            supplierId: (int) $data['supplier_id'],
            purchaseInvoiceId: isset($data['purchase_invoice_id']) ? (int) $data['purchase_invoice_id'] : null,
            items: $items,
            refundAmount: (float) ($data['refund_amount'] ?? 0),
            createdBy: $createdBy,
            notes: $data['notes'] ?? null,
        );
    }
}
