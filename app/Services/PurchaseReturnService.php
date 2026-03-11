<?php

namespace App\Services;

use App\Domain\Store\DTOs\CreatePurchaseReturnDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\StockMovementType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\FinancialTransaction;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(private CacheService $cacheService) {}

    public function create(CreatePurchaseReturnDTO $dto): PurchaseReturn
    {
        return DB::transaction(function () use ($dto) {
            if ($dto->purchaseInvoiceId) {
                PurchaseInvoice::where('store_id', $dto->storeId)
                    ->where('supplier_id', $dto->supplierId)
                    ->findOrFail($dto->purchaseInvoiceId);
            }

            $total = $this->calculateTotal($dto->items);
            $refund = (float) max($dto->refundAmount, 0);

            if ($refund > $total) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'المبلغ النقدي المستلم لا يمكن أن يتجاوز إجمالي المرتجع.',
                ]);
            }

            $return = PurchaseReturn::create([
                'store_id' => $dto->storeId,
                'supplier_id' => $dto->supplierId,
                'purchase_invoice_id' => $dto->purchaseInvoiceId,
                'return_number' => PurchaseReturn::generateNumber($dto->storeId),
                'total_amount' => $total,
                'refund_amount' => $refund,
                'remaining_amount' => round($total - $refund, 2),
                'notes' => $dto->notes,
                'created_by' => $dto->createdBy,
            ]);

            $affectedProductIds = [];
            foreach ($dto->items as $item) {
                $variant = ProductVariant::where('store_id', $dto->storeId)
                    ->with(['product' => fn($query) => $query
                        ->withoutGlobalScopes()
                        ->withTrashed()
                        ->select(['id', 'name'])])
                    ->findOrFail($item->variantId);

                $affectedProductIds[] = (int) $variant->product_id;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'variant_id' => $variant->id,
                    'product_name' => $variant->product?->name ?? 'منتج محذوف',
                    'variant_name' => $variant->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'total_amount' => round($item->quantity * $item->unitPrice, 2),
                ]);

                StockMovement::create([
                    'store_id' => $dto->storeId,
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'type' => StockMovementType::OUT,
                    'quantity' => $item->quantity,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $return->id,
                    'notes' => "مرتجع شراء رقم {$return->return_number}",
                    'created_by' => $dto->createdBy,
                ]);
            }

            FinancialTransaction::create([
                'store_id' => $dto->storeId,
                'party_type' => PartyType::SUPPLIER,
                'party_id' => $dto->supplierId,
                'type' => TransactionType::CREDIT,
                'amount' => $total,
                'reference_type' => 'purchase_return',
                'reference_id' => $return->id,
                'description' => "مرتجع شراء رقم {$return->return_number}",
                'created_by' => $dto->createdBy,
            ]);

            if ($refund > 0) {
                CashTransaction::create([
                    'store_id' => $dto->storeId,
                    'type' => CashTransactionType::IN,
                    'amount' => $refund,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $return->id,
                    'description' => "استلام نقدية من المورد - مرتجع {$return->return_number}",
                    'transaction_date' => today(),
                    'created_by' => $dto->createdBy,
                ]);

                FinancialTransaction::create([
                    'store_id' => $dto->storeId,
                    'party_type' => PartyType::SUPPLIER,
                    'party_id' => $dto->supplierId,
                    'type' => TransactionType::DEBIT,
                    'amount' => $refund,
                    'reference_type' => 'purchase_return_refund',
                    'reference_id' => $return->id,
                    'description' => "استرداد نقدي - مرتجع {$return->return_number}",
                    'created_by' => $dto->createdBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: $affectedProductIds,
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateSupplierBalance($dto->supplierId);
            if ($refund > 0) {
                $this->cacheService->invalidateCashBalance($dto->storeId);
            }

            return $return->load('items.variant.product.category', 'supplier', 'invoice', 'createdBy');
        });
    }

    public function list(int $storeId, array $filters = []): Collection
    {
        $query = PurchaseReturn::query()
            ->with(['supplier:id,name,phone', 'invoice:id,invoice_number'])
            ->withCount('items')
            ->where('store_id', $storeId);

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', $search)
                    ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                        $supplierQuery->where('name', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
            });
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'] . ' 00:00:00');
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'] . ' 23:59:59');
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(PurchaseReturn $r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'supplier_name' => $r->supplier?->name,
                'invoice_number' => $r->invoice?->invoice_number ?? '—',
                'total_amount' => $r->total_amount,
                'refund_amount' => $r->refund_amount,
                'remaining_amount' => $r->remaining_amount,
                'items_count' => $r->items_count,
                'date' => optional($r->created_at)->format('Y-m-d'),
            ]);
    }

    private function calculateTotal(array $items): float
    {
        return round(array_sum(
            array_map(fn($item) => $item->quantity * $item->unitPrice, $items)
        ), 2);
    }
}
