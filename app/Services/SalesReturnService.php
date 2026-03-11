<?php

namespace App\Services;

use App\Domain\Store\DTOs\CreateSalesReturnDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\StockMovementType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\FinancialTransaction;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public function __construct(private CacheService $cacheService) {}

    public function create(CreateSalesReturnDTO $dto): SalesReturn
    {
        return DB::transaction(function () use ($dto) {
            if ($dto->salesInvoiceId) {
                SalesInvoice::where('store_id', $dto->storeId)
                    ->where('customer_id', $dto->customerId)
                    ->findOrFail($dto->salesInvoiceId);
            }

            $total = $this->calculateTotal($dto->items);
            $refund = (float) max($dto->refundAmount, 0);

            if ($refund > $total) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'المبلغ النقدي المرتجع لا يمكن أن يتجاوز إجمالي المرتجع.',
                ]);
            }

            $return = SalesReturn::create([
                'store_id' => $dto->storeId,
                'customer_id' => $dto->customerId,
                'sales_invoice_id' => $dto->salesInvoiceId,
                'return_number' => SalesReturn::generateNumber($dto->storeId),
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

                SalesReturnItem::create([
                    'sales_return_id' => $return->id,
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
                    'type' => StockMovementType::IN,
                    'quantity' => $item->quantity,
                    'reference_type' => 'sales_return',
                    'reference_id' => $return->id,
                    'notes' => "مرتجع بيع رقم {$return->return_number}",
                    'created_by' => $dto->createdBy,
                ]);
            }

            FinancialTransaction::create([
                'store_id' => $dto->storeId,
                'party_type' => PartyType::CUSTOMER,
                'party_id' => $dto->customerId,
                'type' => TransactionType::CREDIT,
                'amount' => $total,
                'reference_type' => 'sales_return',
                'reference_id' => $return->id,
                'description' => "مرتجع بيع رقم {$return->return_number}",
                'created_by' => $dto->createdBy,
            ]);

            if ($refund > 0) {
                CashTransaction::create([
                    'store_id' => $dto->storeId,
                    'type' => CashTransactionType::OUT,
                    'amount' => $refund,
                    'reference_type' => 'sales_return',
                    'reference_id' => $return->id,
                    'description' => "رد نقدية للعميل - مرتجع {$return->return_number}",
                    'transaction_date' => today(),
                    'created_by' => $dto->createdBy,
                ]);

                FinancialTransaction::create([
                    'store_id' => $dto->storeId,
                    'party_type' => PartyType::CUSTOMER,
                    'party_id' => $dto->customerId,
                    'type' => TransactionType::DEBIT,
                    'amount' => $refund,
                    'reference_type' => 'sales_return_refund',
                    'reference_id' => $return->id,
                    'description' => "رد نقدية - مرتجع {$return->return_number}",
                    'created_by' => $dto->createdBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: $affectedProductIds,
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateCustomerBalance($dto->customerId);
            if ($refund > 0) {
                $this->cacheService->invalidateCashBalance($dto->storeId);
            }

            return $return->load('items.variant.product.category', 'customer', 'invoice', 'createdBy');
        });
    }

    public function list(int $storeId, array $filters = []): Collection
    {
        $query = SalesReturn::query()
            ->with(['customer:id,name,phone', 'invoice:id,invoice_number'])
            ->withCount('items')
            ->where('store_id', $storeId);

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', $search)
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
            });
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
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
            ->map(fn(SalesReturn $r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'customer_name' => $r->customer?->name,
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
