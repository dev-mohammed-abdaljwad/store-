<?php

namespace App\Domain\Store\Interfaces;
use App\Domain\Store\Enums\StockMovementType;
interface IStockRepository {
public function addMovement(
        string            $storeId,
        string            $productId,
        StockMovementType $type,
        float             $quantity,
        string            $referenceType,
        string            $referenceId,
        string            $createdBy,
        ?string           $notes = null,
    ): void;

    /**
     * عكس حركات مخزون مرتبطة بمرجع معين (عند الإلغاء).
     */
    public function reverseMovements(
        string $referenceType,
        string $referenceId,
        string $storeId,
        string $createdBy,
    ): void;
     public function getCurrentStock(string $productId, string $storeId): float;
    
    }


    
