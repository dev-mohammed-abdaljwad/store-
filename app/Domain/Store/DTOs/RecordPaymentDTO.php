<?php
namespace App\Domain\Store\DTOs;
final class RecordPaymentDTO
{
    public function __construct(
        public readonly string  $storeId,
        public readonly string  $partyId,     // customer_id أو supplier_id
        public readonly float   $amount,
        public readonly string  $createdBy,
        public readonly ?string $notes = null,
        public readonly ?string $date  = null, // null = today
    ) {}
      public static function fromArray(array $data, string $storeId, string $createdBy): self
    {
        return new self(
            storeId:   $storeId,
            partyId:   $data['party_id'],
            amount:    (float) $data['amount'],
            createdBy: $createdBy,
            notes:     $data['notes'] ?? null,
            date:      $data['date'] ?? null,
        );
    }
}