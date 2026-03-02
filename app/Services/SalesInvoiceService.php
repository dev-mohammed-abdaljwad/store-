<?php

namespace App\Services;

use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Domain\Store\DTOs\CreateSalesInvoiceDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\StockMovementType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\FinancialTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{

    public function create(CreateSalesInvoiceDTO $dto): SalesInvoice
    {
        return DB::transaction(function () use ($dto) {

            // ── Step 1: التحقق من المخزون لكل منتج ───────────────
            $this->validateStock($dto);

            // ── Step 2: حساب الإجمالي ─────────────────────────────
            $total     = $this->calculateTotal($dto->items);
            $paid      = min($dto->paidAmount, $total);
            $remaining = $total - $paid;

            // ── Step 3: حفظ الفاتورة ──────────────────────────────
            $invoice = SalesInvoice::create([
                'store_id'         => $dto->storeId,
                'invoice_number'   => SalesInvoice::generateNumber($dto->storeId),
                'customer_id'      => $dto->customerId,
                'total_amount'     => $total,
                'paid_amount'      => $paid,
                'remaining_amount' => $remaining,
                'status'           => 'confirmed',
                'notes'            => $dto->notes,
                'created_by'       => $dto->createdBy,
            ]);

            // ── Step 4: حفظ بنود الفاتورة ─────────────────────────
            foreach ($dto->items as $item) {
                $product = Product::findOrFail($item->productId);

                SalesInvoiceItem::create([
                    'invoice_id'   => $invoice->id,
                    'product_id'   => $item->productId,
                    'product_name' => $product->name,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unitPrice,
                    'total_price'  => round($item->quantity * $item->unitPrice, 2),
                ]);

                // ── Step 5: خصم المخزون ───────────────────────────
                StockMovement::create([
                    'store_id'       => $dto->storeId,
                    'product_id'     => $item->productId,
                    'type'           => StockMovementType::OUT,
                    'quantity'       => $item->quantity,
                    'reference_type' => 'sales_invoice',
                    'reference_id'   => $invoice->id,
                    'notes'          => "بيع - فاتورة رقم {$invoice->invoice_number}",
                    'created_by'     => $dto->createdBy,
                ]);
            }

            // ── Step 6: قيد مالي مدين على العميل ─────────────────
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $dto->customerId,
                'type'           => TransactionType::DEBIT,
                'amount'         => $total,
                'reference_type' => 'sales_invoice',
                'reference_id'   => $invoice->id,
                'description'    => "فاتورة بيع رقم {$invoice->invoice_number}",
                'created_by'     => $dto->createdBy,
            ]);

            // ── Step 7: قيد نقدي إذا في مدفوع ────────────────────
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

            return $invoice->load('items', 'customer');
        });
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
                'amount'         => $invoice->total_amount,
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

            return $invoice->load('items', 'customer');
        });
    }

    // ── Private Helpers ──────────────────────────────────────────

    /**
     * التحقق من توافر المخزون لكل منتج في الفاتورة.
     */
    private function validateStock(CreateSalesInvoiceDTO $dto): void
    {
        foreach ($dto->items as $item) {
            $product = Product::findOrFail($item->productId);

            if (! $product->canSell($item->quantity)) {
                throw ValidationException::withMessages([
                    'stock' => "المخزون المتاح من [{$product->name}] هو {$product->current_stock} {$product->unit} فقط.",
                ]);
            }
        }
    }

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
