<?php
namespace App\Domain\Store\Repositories;

use App\Domain\Store\Interfaces\IStockRepository;
use App\Domain\Store\Enums\StockMovementType;
use App\Models\StockMovement;

class StockRepository implements IStockRepository
{
    public function addMovement(
        string $storeId,
        string $productId,
        StockMovementType $type,
        float $quantity,
        string $referenceType,
        string $referenceId,
        string $createdBy,
        ?string $notes = null
    ): void {
        StockMovement::create([
            'store_id' => $storeId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
            'notes' => $notes,
        ]);
    }

    public function reverseMovements(
        string $referenceType,
        string $referenceId,
        string $storeId,
        string $createdBy
    ): void {
        StockMovement::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('store_id', $storeId)
            ->update(['reversed_by' => $createdBy, 'is_reversed' => true]);
    }

    public function getCurrentStock(string $productId, string $storeId): float
    {
        $in = StockMovement::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('type', StockMovementType::IN)
            ->sum('quantity');
        $out = StockMovement::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('type', StockMovementType::OUT)
            ->sum('quantity');
        return $in - $out;
    }
}
