<?php

namespace App\Services;

use App\Domain\Store\DTOs\CreatePurchaseInvoiceDTO;
use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\StockMovementType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockMovement;
use App\Models\FinancialTransaction;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    public function __construct(private CacheService $cacheService) {}

    public function create(CreatePurchaseInvoiceDTO $dto): PurchaseInvoice
    {
        return DB::transaction(function () use ($dto) {

            // ── Step 1: حساب الإجمالي بـ received_quantity ────────
            $total     = $this->calculateTotal($dto->items);
            $paid      = min($dto->paidAmount, $total);
            $remaining = $total - $paid;

            // ── Step 2: حفظ الفاتورة ──────────────────────────────
            $invoice = PurchaseInvoice::create([
                'store_id'         => $dto->storeId,
                'invoice_number'   => $dto->invoiceNumber,
                'supplier_id'      => $dto->supplierId,
                'total_amount'     => $total,
                'paid_amount'      => $paid,
                'remaining_amount' => $remaining,
                'status'           => 'confirmed',
                'notes'            => $dto->notes,
                'created_by'       => $dto->createdBy,
            ]);

            // ── Step 3: حفظ البنود + إضافة المخزون ───────────────
            $affectedProductIds = [];
            foreach ($dto->items as $item) {
                $variant = ProductVariant::where('store_id', $dto->storeId)
                    ->with('product:id,name')
                    ->findOrFail($item->variantId);

                $affectedProductIds[] = (int) $variant->product_id;

                PurchaseInvoiceItem::create([
                    'invoice_id'        => $invoice->id,
                    'product_id'        => $variant->product_id,
                    'variant_id'        => $variant->id,
                    'product_name'      => $variant->product?->name,
                    'variant_name'      => $variant->name,
                    'ordered_quantity'  => $item->orderedQuantity,
                    'received_quantity' => $item->receivedQuantity,
                    'unit_price'        => $item->unitPrice,
                    'total_price'       => round($item->receivedQuantity * $item->unitPrice, 2),
                ]);

                // إضافة المخزون بـ received_quantity فقط
                if ($item->receivedQuantity > 0) {
                    StockMovement::create([
                        'store_id'       => $dto->storeId,
                        'product_id'     => $variant->product_id,
                        'variant_id'     => $variant->id,
                        'type'           => StockMovementType::IN,
                        'quantity'       => $item->receivedQuantity,
                        'reference_type' => 'purchase_invoice',
                        'reference_id'   => $invoice->id,
                        'notes'          => "شراء - فاتورة رقم {$invoice->invoice_number}",
                        'created_by'     => $dto->createdBy,
                    ]);
                }
            }

            // ── Step 4: قيد مالي مدين على المتجر للمورد ──────────
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::SUPPLIER,
                'party_id'       => $dto->supplierId,
                'type'           => TransactionType::DEBIT,
                'amount'         => $total,
                'reference_type' => 'purchase_invoice',
                'reference_id'   => $invoice->id,
                'description'    => "فاتورة شراء رقم {$invoice->invoice_number}",
                'created_by'     => $dto->createdBy,
            ]);

            // ── Step 5: قيد نقدي صادر إذا في مدفوع ──────────────
            if ($paid > 0) {
                CashTransaction::create([
                    'store_id'         => $dto->storeId,
                    'type'             => CashTransactionType::OUT,
                    'amount'           => $paid,
                    'reference_type'   => 'purchase_invoice',
                    'reference_id'     => $invoice->id,
                    'description'      => "دفع للمورد - فاتورة {$invoice->invoice_number}",
                    'transaction_date' => today(),
                    'created_by'       => $dto->createdBy,
                ]);

                // قيد credit للمورد بالمبلغ المدفوع
                FinancialTransaction::create([
                    'store_id'       => $dto->storeId,
                    'party_type'     => PartyType::SUPPLIER,
                    'party_id'       => $dto->supplierId,
                    'type'           => TransactionType::CREDIT,
                    'amount'         => $paid,
                    'reference_type' => 'purchase_invoice_payment',
                    'reference_id'   => $invoice->id,
                    'description'    => "دفعة نقدية للمورد - فاتورة {$invoice->invoice_number}",
                    'created_by'     => $dto->createdBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: $affectedProductIds,
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateSupplierBalance($dto->supplierId);
            if ($paid > 0) {
                $this->cacheService->invalidateCashBalance($dto->storeId);
            }

            return $invoice->load('items', 'supplier');
        });
    }

    public function cancel(CancelInvoiceDTO $dto): PurchaseInvoice
    {
        return DB::transaction(function () use ($dto) {

            $invoice = PurchaseInvoice::where('store_id', $dto->storeId)
                                      ->findOrFail($dto->invoiceId);

            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => 'الفاتورة ملغاة مسبقاً.',
                ]);
            }

            // ── Step 1: تغيير الحالة ──────────────────────────────
            $invoice->update([
                'status'        => 'cancelled',
                'cancel_reason' => $dto->reason,
                'cancelled_by'  => $dto->cancelledBy,
                'cancelled_at'  => now(),
            ]);

            // ── Step 2: عكس المخزون ───────────────────────────────
            foreach ($invoice->items as $item) {
                if ($item->received_quantity > 0) {
                    StockMovement::create([
                        'store_id'       => $dto->storeId,
                        'product_id'     => $item->product_id,
                        'variant_id'     => $item->variant_id,
                        'type'           => StockMovementType::OUT,
                        'quantity'       => $item->received_quantity,
                        'reference_type' => 'purchase_invoice_cancel',
                        'reference_id'   => $invoice->id,
                        'notes'          => "إلغاء فاتورة شراء {$invoice->invoice_number}",
                        'created_by'     => $dto->cancelledBy,
                    ]);
                }
            }

            // ── Step 3: عكس القيد المالي للمورد ──────────────────
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::SUPPLIER,
                'party_id'       => $invoice->supplier_id,
                'type'           => TransactionType::CREDIT,
                'amount'         => $invoice->total_amount,
                'reference_type' => 'purchase_invoice_cancel',
                'reference_id'   => $invoice->id,
                'description'    => "إلغاء فاتورة شراء {$invoice->invoice_number}",
                'created_by'     => $dto->cancelledBy,
            ]);

            // ── Step 4: عكس النقدية إذا كان في مدفوع ────────────
            if ($invoice->paid_amount > 0) {
                CashTransaction::create([
                    'store_id'         => $dto->storeId,
                    'type'             => CashTransactionType::IN,
                    'amount'           => $invoice->paid_amount,
                    'reference_type'   => 'purchase_invoice_cancel',
                    'reference_id'     => $invoice->id,
                    'description'      => "استرداد دفعة - إلغاء فاتورة {$invoice->invoice_number}",
                    'transaction_date' => today(),
                    'created_by'       => $dto->cancelledBy,
                ]);

                // عكس الـ credit اللي اتسجل للمورد
                FinancialTransaction::create([
                    'store_id'       => $dto->storeId,
                    'party_type'     => PartyType::SUPPLIER,
                    'party_id'       => $invoice->supplier_id,
                    'type'           => TransactionType::DEBIT,
                    'amount'         => $invoice->paid_amount,
                    'reference_type' => 'purchase_invoice_cancel',
                    'reference_id'   => $invoice->id,
                    'description'    => "عكس دفعة - إلغاء فاتورة {$invoice->invoice_number}",
                    'created_by'     => $dto->cancelledBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: $invoice->items->pluck('product_id')->all(),
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateSupplierBalance((int) $invoice->supplier_id);
            if ($invoice->paid_amount > 0) {
                $this->cacheService->invalidateCashBalance($dto->storeId);
            }

            return $invoice->load('items', 'supplier');
        });
    }

    private function calculateTotal(array $items): float
    {
        return round(array_sum(
            array_map(fn($i) => $i->receivedQuantity * $i->unitPrice, $items)
        ), 2);
    }
}