<?php

namespace App\Services;

use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Domain\Store\DTOs\CreateSalesInvoiceDTO;
use App\Domain\Store\DTOs\UpdateSalesInvoiceDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\StockMovementType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\FinancialTransaction;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(private CacheService $cacheService) {}

    public function create(CreateSalesInvoiceDTO $dto): SalesInvoice
    {
        return DB::transaction(function () use ($dto) {
            // ── Step 1: حساب الإجمالي ─────────────────────────────
            $total = $this->calculateTotal($dto->items);
            $discount = max($dto->discountAmount, 0);
            $net = max($total - $discount, 0);
            $paid = min($dto->paidAmount, $net);
            $remaining = $net - $paid;

            $invoice = SalesInvoice::create([
                'store_id'         => $dto->storeId,
                'invoice_number'   => $dto->invoiceNumber,
                'invoice_date'     => $dto->invoiceDate,
                'customer_id'      => $dto->customerId,
                'total_amount'     => $total,
                'discount_amount'  => $discount,
                'net_amount'       => $net,
                'paid_amount'      => $paid,
                'remaining_amount' => $remaining,
                'status'           => 'confirmed',
                'notes'            => $dto->notes,
                'sales_rep_name'   => $dto->salesRepName,
                'created_by'       => $dto->createdBy,
            ]);

            // ── Step 3: حفظ بنود الفاتورة ─────────────────────────
            $affectedProductIds = [];
            foreach ($dto->items as $item) {
                $variant = ProductVariant::where('store_id', $dto->storeId)
                    ->with(['product' => fn ($query) => $query
                        ->withoutGlobalScopes()
                        ->withTrashed()
                        ->select(['id', 'store_id', 'name'])])
                    ->findOrFail($item->variantId);

                $productName = $variant->product?->name;
                if (blank($productName)) {
                    throw ValidationException::withMessages([
                        'items' => 'لا يمكن إنشاء الفاتورة لأن بيانات المنتج المرتبط بأحد الأحجام غير مكتملة.',
                    ]);
                }

                $affectedProductIds[] = (int) $variant->product_id;

                SalesInvoiceItem::create([
                    'invoice_id'   => $invoice->id,
                    'product_id'   => $variant->product_id,
                    'variant_id'   => $variant->id,
                    'product_name' => $productName,
                    'variant_name' => $variant->name,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unitPrice,
                    'total_price'  => round($item->quantity * $item->unitPrice, 2),
                ]);

                // ── Step 4: خصم المخزون ───────────────────────────
                StockMovement::create([
                    'store_id'       => $dto->storeId,
                    'product_id'     => $variant->product_id,
                    'variant_id'     => $variant->id,
                    'type'           => StockMovementType::OUT,
                    'quantity'       => $item->quantity,
                    'reference_type' => 'sales_invoice',
                    'reference_id'   => $invoice->id,
                    'notes'          => "بيع - فاتورة رقم {$invoice->invoice_number}",
                    'created_by'     => $dto->createdBy,
                ]);
            }

            // ── Step 5: قيد مالي مدين على العميل ─────────────────
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $dto->customerId,
                'type'           => TransactionType::DEBIT,
                'amount'         => $net,
                'reference_type' => 'sales_invoice',
                'reference_id'   => $invoice->id,
                'description'    => "فاتورة بيع رقم {$invoice->invoice_number}",
                'created_by'     => $dto->createdBy,
            ]);

            // ── Step 6: قيد نقدي إذا في مدفوع ────────────────────
            if ($paid > 0) {
                $this->recordCashIn(
                    storeId: $dto->storeId,
                    amount: $paid,
                    referenceType: 'sales_invoice',
                    referenceId: $invoice->id,
                    description: "تحصيل نقدي - فاتورة {$invoice->invoice_number}",
                    createdBy: $dto->createdBy,
                );

                // قيد credit للعميل بالمبلغ المدفوع
                FinancialTransaction::create([
                    'store_id'       => $dto->storeId,
                    'party_type'     => PartyType::CUSTOMER,
                    'party_id'       => $dto->customerId,
                    'type'           => TransactionType::CREDIT,
                    'amount'         => $paid,
                    'reference_type' => 'sales_invoice_payment',
                    'reference_id'   => $invoice->id,
                    'description'    => "دفعة نقدية - فاتورة {$invoice->invoice_number}",
                    'created_by'     => $dto->createdBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: $affectedProductIds,
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateCustomerBalance($dto->customerId);
            if ($paid > 0) {
                $this->cacheService->invalidateCashBalance($dto->storeId);
            }

            return $invoice->load('items.variant.product.category', 'customer');
        });
    }

    public function update(UpdateSalesInvoiceDTO $dto): SalesInvoice
    {
        return DB::transaction(function () use ($dto) {
            $invoice = SalesInvoice::where('store_id', $dto->storeId)
                ->findOrFail($dto->invoiceId);

            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => 'لا يمكن تعديل فاتورة ملغاة.',
                ]);
            }

            // ── Step 1: حذف الحركات القديمة ───────────────────────
            $oldProductIds = $invoice->items()->pluck('product_id')->toArray();
            
            $invoice->items()->delete();
            StockMovement::where('reference_type', 'sales_invoice')
                ->where('reference_id', $invoice->id)
                ->delete();
            FinancialTransaction::whereIn('reference_type', ['sales_invoice', 'sales_invoice_payment'])
                ->where('reference_id', $invoice->id)
                ->delete();
            CashTransaction::where('reference_type', 'sales_invoice')
                ->where('reference_id', $invoice->id)
                ->delete();

            // ── Step 2: حساب الإجمالي الجديد ────────────────────────
            $total = $this->calculateTotal($dto->items);
            $discount = max($dto->discountAmount, 0);
            $net = max($total - $discount, 0);
            $paid = min($dto->paidAmount, $net);
            $remaining = $net - $paid;

            // ── Step 3: تحديث الفاتورة ──────────────────────────────
            $invoice->update([
                'invoice_number'   => $dto->invoiceNumber,
                'invoice_date'     => $dto->invoiceDate,
                'customer_id'      => $dto->customerId,
                'total_amount'     => $total,
                'discount_amount'  => $discount,
                'net_amount'       => $net,
                'paid_amount'      => $paid,
                'remaining_amount' => $remaining,
                'notes'            => $dto->notes,
                'sales_rep_name'   => $dto->salesRepName,
            ]);

            // ── Step 4: حفظ بنود الفاتورة الجديدة ───────────────────
            $affectedProductIds = $oldProductIds;
            foreach ($dto->items as $item) {
                $variant = ProductVariant::where('store_id', $dto->storeId)
                    ->with(['product' => fn ($query) => $query
                        ->withoutGlobalScopes()
                        ->withTrashed()
                        ->select(['id', 'store_id', 'name'])])
                    ->findOrFail($item->variantId);

                $productName = $variant->product?->name;
                if (blank($productName)) {
                    throw ValidationException::withMessages([
                        'items' => 'لا يمكن تعديل الفاتورة لأن بيانات المنتج المرتبط بأحد الأحجام غير مكتملة.',
                    ]);
                }

                $affectedProductIds[] = (int) $variant->product_id;

                SalesInvoiceItem::create([
                    'invoice_id'   => $invoice->id,
                    'product_id'   => $variant->product_id,
                    'variant_id'   => $variant->id,
                    'product_name' => $productName,
                    'variant_name' => $variant->name,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unitPrice,
                    'total_price'  => round($item->quantity * $item->unitPrice, 2),
                ]);

                // خصم المخزون
                StockMovement::create([
                    'store_id'       => $dto->storeId,
                    'product_id'     => $variant->product_id,
                    'variant_id'     => $variant->id,
                    'type'           => StockMovementType::OUT,
                    'quantity'       => $item->quantity,
                    'reference_type' => 'sales_invoice',
                    'reference_id'   => $invoice->id,
                    'notes'          => "بيع (تعديل) - فاتورة رقم {$invoice->invoice_number}",
                    'created_by'     => $dto->updatedBy,
                ]);
            }

            // ── Step 5: قيد مالي مدين جديد على العميل ─────────────
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $dto->customerId,
                'type'           => TransactionType::DEBIT,
                'amount'         => $net,
                'reference_type' => 'sales_invoice',
                'reference_id'   => $invoice->id,
                'description'    => "فاتورة بيع رقم {$invoice->invoice_number} (معدلة)",
                'created_by'     => $dto->updatedBy,
            ]);

            // ── Step 6: قيد نقدي جديد إذا في مدفوع ────────────────
            if ($paid > 0) {
                $this->recordCashIn(
                    storeId: $dto->storeId,
                    amount: $paid,
                    referenceType: 'sales_invoice',
                    referenceId: $invoice->id,
                    description: "تحصيل نقدي - فاتورة {$invoice->invoice_number} (معدلة)",
                    createdBy: $dto->updatedBy,
                );

                FinancialTransaction::create([
                    'store_id'       => $dto->storeId,
                    'party_type'     => PartyType::CUSTOMER,
                    'party_id'       => $dto->customerId,
                    'type'           => TransactionType::CREDIT,
                    'amount'         => $paid,
                    'reference_type' => 'sales_invoice_payment',
                    'reference_id'   => $invoice->id,
                    'description'    => "دفعة نقدية - فاتورة {$invoice->invoice_number} (معدلة)",
                    'created_by'     => $dto->updatedBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: array_unique($affectedProductIds),
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateCustomerBalance($dto->customerId);
            if ($invoice->customer_id != $dto->customerId) {
                $this->cacheService->invalidateCustomerBalance($invoice->customer_id);
            }
            $this->cacheService->invalidateCashBalance($dto->storeId);

            return $invoice->load('items.variant.product.category', 'customer');
        });
    }

    public function getInvoiceDeficits(SalesInvoice $invoice): array
    {
        return $invoice->items
            ->map(function (SalesInvoiceItem $item) {
                $variant = ProductVariant::withoutGlobalScopes()
                    ->with(['product' => fn ($query) => $query
                        ->withoutGlobalScopes()
                        ->withTrashed()
                        ->select(['id', 'name'])])
                    ->find($item->variant_id);

                if (! $variant || ! $variant->hasDeficit()) {
                    return null;
                }

                $productName = $variant->product?->name ?? $item->product_name;

                return [
                    'variant_id' => $variant->id,
                    'product_name' => $productName,
                    'variant_name' => $variant->name,
                    'deficit' => $variant->getDeficit(),
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function cancel(CancelInvoiceDTO $dto): SalesInvoice
    {
        return DB::transaction(function () use ($dto) {

            $invoice = SalesInvoice::where('store_id', $dto->storeId)
                ->findOrFail($dto->invoiceId);

            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => 'الفاتورة ملغاة مسبقاً.',
                ]);
            }

            // ── Step 1: تغيير حالة الفاتورة ──────────────────────
            $invoice->update([
                'status'       => 'cancelled',
                'cancel_reason' => $dto->reason,
                'cancelled_by' => $dto->cancelledBy,
                'cancelled_at' => now(),
            ]);

            // ── Step 2: عكس المخزون ───────────────────────────────
            foreach ($invoice->items as $item) {
                StockMovement::create([
                    'store_id'       => $dto->storeId,
                    'product_id'     => $item->product_id,
                    'variant_id'     => $item->variant_id,
                    'type'           => StockMovementType::IN,
                    'quantity'       => $item->quantity,
                    'reference_type' => 'sales_invoice_cancel',
                    'reference_id'   => $invoice->id,
                    'notes'          => "إلغاء فاتورة رقم {$invoice->invoice_number}",
                    'created_by'     => $dto->cancelledBy,
                ]);
            }

            // ── Step 3: عكس القيد المالي (إلغاء الدين) ───────────
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $invoice->customer_id,
                'type'           => TransactionType::CREDIT,
                'amount'         => $invoice->net_amount,
                'reference_type' => 'sales_invoice_cancel',
                'reference_id'   => $invoice->id,
                'description'    => "إلغاء فاتورة رقم {$invoice->invoice_number}",
                'created_by'     => $dto->cancelledBy,
            ]);

            // ── Step 4: التعامل مع المبلغ المدفوع ────────────────
            if ($invoice->paid_amount > 0) {

                // عكس القيد النقدي
                $this->recordCashOut(
                    storeId: $dto->storeId,
                    amount: $invoice->paid_amount,
                    referenceType: 'sales_invoice_cancel',
                    referenceId: $invoice->id,
                    description: "عكس تحصيل - إلغاء فاتورة {$invoice->invoice_number}",
                    createdBy: $dto->cancelledBy,
                );

                // عكس الـ credit اللي اتسجل وقت الدفع
                FinancialTransaction::create([
                    'store_id'       => $dto->storeId,
                    'party_type'     => PartyType::CUSTOMER,
                    'party_id'       => $invoice->customer_id,
                    'type'           => TransactionType::DEBIT,
                    'amount'         => $invoice->paid_amount,
                    'reference_type' => 'sales_invoice_cancel',
                    'reference_id'   => $invoice->id,
                    'description'    => "عكس دفعة - إلغاء فاتورة {$invoice->invoice_number}",
                    'created_by'     => $dto->cancelledBy,
                ]);

                // تحويل المبلغ المدفوع لرصيد دائن للعميل
                FinancialTransaction::create([
                    'store_id'       => $dto->storeId,
                    'party_type'     => PartyType::CUSTOMER,
                    'party_id'       => $invoice->customer_id,
                    'type'           => TransactionType::CREDIT,
                    'amount'         => $invoice->paid_amount,
                    'reference_type' => 'customer_credit',
                    'reference_id'   => $invoice->id,
                    'description'    => "رصيد دائن من إلغاء فاتورة {$invoice->invoice_number}",
                    'created_by'     => $dto->cancelledBy,
                ]);
            }

            $this->cacheService->invalidateStock(
                storeId: $dto->storeId,
                productIds: $invoice->items->pluck('product_id')->all(),
            );
            $this->cacheService->invalidateProductsDropdown($dto->storeId);
            $this->cacheService->invalidateCustomerBalance((int) $invoice->customer_id);
            if ($invoice->paid_amount > 0) {
                $this->cacheService->invalidateCashBalance($dto->storeId);
            }

            return $invoice->load('items.variant.product.category', 'customer');
        });
    }

    public function delete(int $storeId, int $invoiceId, int $deletedBy): SalesInvoice
    {
        return DB::transaction(function () use ($storeId, $invoiceId) {
            $invoice = SalesInvoice::with('items', 'returns')
                ->where('store_id', $storeId)
                ->findOrFail($invoiceId);

            if ($invoice->returns()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'لا يمكن حذف الفاتورة لأنها لها مرتجعات مرتبطة بها.',
                ]);
            }

            StockMovement::where('reference_type', 'sales_invoice')
                ->where('reference_id', $invoice->id)
                ->delete();

            StockMovement::where('reference_type', 'sales_invoice_cancel')
                ->where('reference_id', $invoice->id)
                ->delete();

            FinancialTransaction::whereIn('reference_type', [
                    'sales_invoice',
                    'sales_invoice_payment',
                    'sales_invoice_cancel',
                    'customer_credit',
                ])
                ->where('reference_id', $invoice->id)
                ->delete();

            CashTransaction::whereIn('reference_type', [
                    'sales_invoice',
                    'sales_invoice_cancel',
                ])
                ->where('reference_id', $invoice->id)
                ->delete();

            $invoice->forceDelete();

            return $invoice;
        });
    }

    // ── Private Helpers ──────────────────────────────────────────

    private function calculateTotal(array $items): float
    {
        return round(array_sum(
            array_map(fn($i) => $i->quantity * $i->unitPrice, $items)
        ), 2);
    }

    private function recordCashIn(
        int    $storeId,
        float  $amount,
        string $referenceType,
        int    $referenceId,
        string $description,
        int    $createdBy,
    ): void {
        CashTransaction::create([
            'store_id'         => $storeId,
            'type'             => CashTransactionType::IN,
            'amount'           => $amount,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'description'      => $description,
            'transaction_date' => today(),
            'created_by'       => $createdBy,
        ]);
    }

    private function recordCashOut(
        int    $storeId,
        float  $amount,
        string $referenceType,
        int    $referenceId,
        string $description,
        int    $createdBy,
    ): void {
        CashTransaction::create([
            'store_id'         => $storeId,
            'type'             => CashTransactionType::OUT,
            'amount'           => $amount,
            'reference_type'   => $referenceType,
            'reference_id'     => $referenceId,
            'description'      => $description,
            'transaction_date' => today(),
            'created_by'       => $createdBy,
        ]);
    }
}
