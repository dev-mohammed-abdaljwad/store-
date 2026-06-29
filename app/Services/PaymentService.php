<?php

// ══════════════════════════════════════════════════════════════════
// PaymentService.php — تحصيل وسداد نقدي بدون فاتورة
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Domain\Store\DTOs\RecordPaymentDTO;
use App\Domain\Store\Enums\CashTransactionType;
use App\Domain\Store\Enums\PartyType;
use App\Domain\Store\Enums\TransactionType;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentService
{
    public function __construct(private CacheService $cacheService) {}

    /**
     * Apply a payment to a sales invoice (customer payment).
     */
    public function collectFromCustomer(RecordPaymentDTO $dto): ?Payment
    {
        $createdPayment = null;

        DB::transaction(function () use ($dto, &$createdPayment) {

            $invoice = \App\Models\SalesInvoice::where('store_id', $dto->storeId)->find($dto->invoiceId);

            if ($dto->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
                ]);
            }

            // Determine customer id: if invoiceId > 0 use invoice, otherwise use provided partyId for direct payment
            if (($dto->invoiceId ?? 0) > 0) {
                $customerId = $invoice->customer_id;
            } else {
                if (!$dto->partyId) {
                    throw ValidationException::withMessages(['party_id' => 'يرجى تحديد العميل عند إنشاء قيد تحصيل مباشر.']);
                }
                $customerId = $dto->partyId;
            }

            // قيد credit للعميل (يُقلل دينه) مع إشارة إلى الفاتورة
            // For direct payments we create a Payment entity and reference it.
            if (($dto->invoiceId ?? 0) > 0) {
                $refType = 'sales_invoice';
                $refId = $invoice->id;
                $description = $dto->notes ?? "تحصيل من الفاتورة: {$invoice->invoice_number}";
            } else {
                // create Payment entity for direct payment
                $paymentNumber = $dto->receiptNumber;
                if (empty($paymentNumber)) {
                    $year = now()->year;
                    $last = Payment::withoutGlobalScopes()
                        ->where('store_id', $dto->storeId)
                        ->whereYear('created_at', $year)
                        ->count();
                    $paymentNumber = sprintf('PM-%d-%04d', $year, $last + 1);
                }

                $payment = Payment::create([
                    'store_id' => $dto->storeId,
                    'party_type' => PartyType::CUSTOMER,
                    'party_id' => $customerId,
                    'amount' => $dto->amount,
                    'payment_number' => $paymentNumber,
                    'payment_date' => $dto->date ?? today(),
                    'description' => $dto->notes ?? null,
                    'receipt_number' => $dto->receiptNumber ?? null,
                    'created_by' => $dto->createdBy,
                ]);

                $refType = 'payment';
                $refId = $payment->id;
                $description = $dto->notes ?? "تحصيل نقدي مباشر من العميل: {$customerId}";
                $createdPayment = $payment;
            }

            // Generate receipt number when not provided
            $receiptNumber = $dto->receiptNumber;
            if (empty($receiptNumber)) {
                $year = now()->year;
                $last = FinancialTransaction::withoutGlobalScopes()
                    ->where('store_id', $dto->storeId)
                    ->whereYear('created_at', $year)
                    ->whereNotNull('receipt_number')
                    ->count();
                $receiptNumber = sprintf('RC-%d-%04d', $year, $last + 1);
            }

            $ft = FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $customerId,
                'type'           => TransactionType::CREDIT,
                'amount'         => $dto->amount,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'receipt_number' => $receiptNumber,
                'created_by'     => $dto->createdBy,
            ]);

            // قيد نقدي وارد مرتبط بالفاتورة أو كقيد مباشر
            CashTransaction::create([
                'store_id'         => $dto->storeId,
                'type'             => CashTransactionType::IN,
                'amount'           => $dto->amount,
                'reference_type'   => $refType,
                'reference_id'     => $refId,
                'description'      => $description,
                'transaction_date' => $dto->date ?? today(),
                'created_by'       => $dto->createdBy,
            ]);

            // تحديث رصيد الفاتورة
            // Update invoice balances only when invoice-linked
            if (($dto->invoiceId ?? 0) > 0) {
                $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $dto->amount;
                $invoice->remaining_amount = max(0, ($invoice->remaining_amount ?? ($invoice->total_amount - ($invoice->paid_amount ?? 0))) - $dto->amount);
                $invoice->save();
            }

            $this->cacheService->invalidateCustomerBalance($customerId);
            $this->cacheService->invalidateCashBalance($dto->storeId);
        });

        return $createdPayment;
    }

    /**
     * List direct payments for a party (customer or supplier).
     * Returns FinancialTransaction records representing direct payments.
     */
    public function listDirectPayments(int $storeId, PartyType|string $partyType, int $partyId, int $perPage = 50): LengthAwarePaginator
    {
        $partyTypeValue = $partyType instanceof PartyType ? $partyType->value : $partyType;

        // Gather financial transactions and prefer the cash transaction's `transaction_date` when available
        $fts = FinancialTransaction::where('store_id', $storeId)
            ->where('party_type', $partyTypeValue)
            ->where('party_id', $partyId)
            ->whereIn('reference_type', ['direct_payment', 'sales_invoice_payment', 'purchase_invoice_payment'])
            ->get();

        // Bulk-load party name for this party type
        $partyIds = $fts->pluck('party_id')->filter()->unique()->values()->all();
        $names = [];
        if ($partyTypeValue === PartyType::CUSTOMER->value) {
            $names = Customer::whereIn('id', $partyIds)->pluck('name', 'id')->toArray();
        } else {
            $names = Supplier::whereIn('id', $partyIds)->pluck('name', 'id')->toArray();
        }

        $items = $fts->map(function ($ft) use ($storeId, $names) {
            $cash = CashTransaction::where('store_id', $storeId)
                ->where('reference_type', $ft->reference_type)
                ->where('reference_id', $ft->reference_id)
                ->first();

            $paymentDate = $cash?->transaction_date?->toDateString() ?? $ft->created_at?->toDateString();
            $sortDate = $cash?->transaction_date ?? $ft->created_at;

            return [
                'id' => $ft->id,
                'payment_number' => $ft->receipt_number,
                'payment_date' => $paymentDate,
                'amount' => $ft->amount,
                'description' => $ft->description,
                'party_type' => $ft->party_type,
                'party_id' => $ft->party_id,
                'party_name' => $names[$ft->party_id] ?? null,
                'reference_type' => $ft->reference_type,
                'reference_id' => $ft->reference_id,
                'created_at' => $ft->created_at,
                'sort_date' => $sortDate,
            ];
        })->sortByDesc(fn($i) => $i['created_at'])->values();

        $total = $items->count();
        $page = (int) request()->query('page', 1);
        $paginated = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($paginated, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * List all payments for the store (both Payment entities and legacy direct FinancialTransactions).
     * Returns a LengthAwarePaginator with unified items.
     */
    public function listAllPayments(int $storeId, int $perPage = 50, int $page = 1): LengthAwarePaginator
    {
        // Fetch Payment records
        $paymentsModels = Payment::where('store_id', $storeId)->get();

        // collect party ids per type for bulk name lookup
        $customerIds = $paymentsModels->where('party_type', PartyType::CUSTOMER)->pluck('party_id')->filter()->unique()->values()->all();
        $supplierIds = $paymentsModels->where('party_type', PartyType::SUPPLIER)->pluck('party_id')->filter()->unique()->values()->all();

        $customerNames = Customer::whereIn('id', $customerIds)->pluck('name', 'id')->toArray();
        $supplierNames = Supplier::whereIn('id', $supplierIds)->pluck('name', 'id')->toArray();

        $payments = $paymentsModels->map(function ($p) use ($customerNames, $supplierNames) {
            $partyName = null;
            if ($p->party_type === PartyType::CUSTOMER) $partyName = $customerNames[$p->party_id] ?? null;
            if ($p->party_type === PartyType::SUPPLIER) $partyName = $supplierNames[$p->party_id] ?? null;

            return [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'payment_date' => $p->payment_date?->toDateString(),
                'amount' => $p->amount,
                'description' => $p->description,
                'party_type' => $p->party_type,
                'party_id' => $p->party_id,
                'party_name' => $partyName,
                'reference_type' => 'payment',
                'reference_id' => $p->id,
                'created_at' => $p->created_at,
                'sort_date' => $p->payment_date ?? $p->created_at,
            ];
        });

        // Fetch legacy financial transactions that are direct payments and try to prefer cash transaction date
        $legacyModels = FinancialTransaction::where('store_id', $storeId)
            ->where('reference_type', 'direct_payment')
            ->get();

        // bulk load names for legacy entries
        $legacyCustomerIds = $legacyModels->where('party_type', PartyType::CUSTOMER)->pluck('party_id')->filter()->unique()->values()->all();
        $legacySupplierIds = $legacyModels->where('party_type', PartyType::SUPPLIER)->pluck('party_id')->filter()->unique()->values()->all();

        $legacyCustomerNames = Customer::whereIn('id', $legacyCustomerIds)->pluck('name', 'id')->toArray();
        $legacySupplierNames = Supplier::whereIn('id', $legacySupplierIds)->pluck('name', 'id')->toArray();

        $legacy = $legacyModels->map(function ($ft) use ($storeId, $legacyCustomerNames, $legacySupplierNames) {
            $cash = CashTransaction::where('store_id', $storeId)
                ->where('reference_type', $ft->reference_type)
                ->where('reference_id', $ft->reference_id)
                ->first();

            $paymentDate = $cash?->transaction_date?->toDateString() ?? $ft->created_at?->toDateString();
            $sortDate = $cash?->transaction_date ?? $ft->created_at;

            $partyName = null;
            if ($ft->party_type === PartyType::CUSTOMER) $partyName = $legacyCustomerNames[$ft->party_id] ?? null;
            if ($ft->party_type === PartyType::SUPPLIER) $partyName = $legacySupplierNames[$ft->party_id] ?? null;

            return [
                'id' => $ft->id,
                'payment_number' => $ft->receipt_number,
                'payment_date' => $paymentDate,
                'amount' => $ft->amount,
                'description' => $ft->description,
                'party_type' => $ft->party_type,
                'party_id' => $ft->party_id,
                'party_name' => $partyName,
                'reference_type' => $ft->reference_type,
                'reference_id' => $ft->reference_id,
                'created_at' => $ft->created_at,
                'sort_date' => $sortDate,
            ];
        });

        // Merge and sort by preferred date desc
        $all = collect($payments)->merge(collect($legacy))->sortByDesc(fn($i) => $i['created_at'])->values();

        $total = $all->count();
        $items = $all->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * List payments filtered by party type (customer or supplier).
     */
    public function listPaymentsByPartyType(int $storeId, PartyType|string $partyType, int $perPage = 50, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        $partyTypeValue = $partyType instanceof PartyType ? $partyType->value : $partyType;

        $paymentsQuery = Payment::where('store_id', $storeId)
            ->where('party_type', $partyTypeValue);

        if (!empty($search)) {
            $paymentsQuery->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        $paymentsModels = $paymentsQuery->get();

        $legacyQuery = FinancialTransaction::where('store_id', $storeId)
            ->where('party_type', $partyTypeValue)
            ->where('reference_type', 'direct_payment');

        if (!empty($search)) {
            $legacyQuery->where('receipt_number', 'like', "%{$search}%");
        }

        $legacyModels = $legacyQuery->get();

        $partyIds = $paymentsModels->pluck('party_id')
            ->merge($legacyModels->pluck('party_id'))
            ->filter()->unique()->values()->all();

        $names = $partyTypeValue === PartyType::CUSTOMER->value
            ? Customer::whereIn('id', $partyIds)->pluck('name', 'id')->toArray()
            : Supplier::whereIn('id', $partyIds)->pluck('name', 'id')->toArray();

        $payments = $paymentsModels->map(function ($p) use ($names) {
            return [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'payment_date' => $p->payment_date?->toDateString(),
                'amount' => $p->amount,
                'description' => $p->description,
                'party_type' => $p->party_type,
                'party_id' => $p->party_id,
                'party_name' => $names[$p->party_id] ?? null,
                'reference_type' => 'payment',
                'reference_id' => $p->id,
                'created_at' => $p->created_at,
                'sort_date' => $p->payment_date ?? $p->created_at,
            ];
        });

        $legacy = $legacyModels->map(function ($ft) use ($storeId, $names) {
            $cash = CashTransaction::where('store_id', $storeId)
                ->where('reference_type', $ft->reference_type)
                ->where('reference_id', $ft->reference_id)
                ->first();

            $paymentDate = $cash?->transaction_date?->toDateString() ?? $ft->created_at?->toDateString();
            $sortDate = $cash?->transaction_date ?? $ft->created_at;

            return [
                'id' => $ft->id,
                'payment_number' => $ft->receipt_number,
                'payment_date' => $paymentDate,
                'amount' => $ft->amount,
                'description' => $ft->description,
                'party_type' => $ft->party_type,
                'party_id' => $ft->party_id,
                'party_name' => $names[$ft->party_id] ?? null,
                'reference_type' => $ft->reference_type,
                'reference_id' => $ft->reference_id,
                'created_at' => $ft->created_at,
                'sort_date' => $sortDate,
            ];
        });

        $all = collect($payments)->merge(collect($legacy))->sortByDesc(fn($i) => $i['created_at'])->values();

        $total = $all->count();
        $items = $all->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Update a direct payment (financial transaction) and try to update matching cash transaction.
     */
    public function updateDirectPayment(int $storeId, int $paymentId, array $data): void
    {
        DB::transaction(function () use ($storeId, $paymentId, $data) {
            // If a Payment entity exists with this id, update it and linked transactions
            // But ONLY if this is not a legacy direct_payment record (reference_id = 0)
            $payment = null;
            $ft = FinancialTransaction::where('store_id', $storeId)->find($paymentId);
            
            if ($ft && $ft->reference_type === 'payment' && $ft->reference_id > 0) {
                $payment = Payment::where('store_id', $storeId)->find($ft->reference_id);
            }

            if ($payment) {
                if (isset($data['amount']) && $data['amount'] <= 0) {
                    throw ValidationException::withMessages(['amount' => 'المبلغ يجب أن يكون أكبر من صفر.']);
                }

                $oldAmount = $payment->amount;

                $fillData = [];
                if (array_key_exists('amount', $data) && $data['amount'] !== null)          $fillData['amount']         = $data['amount'];
                if (array_key_exists('description', $data) && $data['description'] !== null) $fillData['description']     = $data['description'];
                if (array_key_exists('receipt_number', $data) && $data['receipt_number'] !== null) $fillData['receipt_number'] = $data['receipt_number'];
                if (array_key_exists('payment_date', $data) && $data['payment_date'] !== null) $fillData['payment_date'] = $data['payment_date'];
                if (array_key_exists('transaction_date', $data) && $data['transaction_date'] !== null) $fillData['payment_date'] = $data['transaction_date'];
                $payment->fill($fillData);
                $payment->save();

                // update financial transactions pointing to this payment
                // add payemtn data to this updates
                $fts = FinancialTransaction::where('store_id', $storeId)
                    ->whereIn('reference_type', ['payment', 'direct_payment'])
                    ->where('reference_id', $payment->id)
                    ->get();

                foreach ($fts as $ftItem) {
                    $ftItem->amount = $data['amount'] ?? $ftItem->amount;
                    if (isset($data['description'])) $ftItem->description = $data['description'];
                    if (isset($data['payment_date'])) $ftItem->payment_date = $data['payment_date'];                    
                    if (isset($data['receipt_number'])) $ftItem->receipt_number = $data['receipt_number'];
                    $ftItem->save();
                }

                // update cash transactions
                $cashs = CashTransaction::where('store_id', $storeId)
                    ->whereIn('reference_type', ['payment', 'direct_payment'])
                    ->where('reference_id', $payment->id)
                    ->get();

                foreach ($cashs as $cash) {
                    $cash->amount = $data['amount'] ?? $cash->amount;
                    if (isset($data['payment_date'])) $cash->payment_date = $data['payment_date'];                    
                    if (isset($data['transaction_date'])) $cash->transaction_date = $data['transaction_date'];
                    if (isset($data['description'])) $cash->description = $data['description'];
                    $cash->save();
                }

                // invalidate caches
                if ($payment->party_type === PartyType::CUSTOMER) {
                    $this->cacheService->invalidateCustomerBalance($payment->party_id);
                } else {
                    $this->cacheService->invalidateSupplierBalance($payment->party_id);
                }
                $this->cacheService->invalidateCashBalance($storeId);

                return;
            }

            // Fallback: If no Payment exists (legacy direct_payment or reference_id = 0)
            if (!$ft) {
                $ft = FinancialTransaction::where('store_id', $storeId)
                    ->where('id', $paymentId)
                    ->firstOrFail();
            }

            if (isset($data['amount']) && $data['amount'] <= 0) {
                throw ValidationException::withMessages(['amount' => 'المبلغ يجب أن يكون أكبر من صفر.']);
            }

            $oldAmount = $ft->amount;

            $ftData = [];
            if (array_key_exists('amount', $data) && $data['amount'] !== null)          $ftData['amount']         = $data['amount'];

            if (array_key_exists('description', $data) && $data['description'] !== null) $ftData['description']     = $data['description'];
            if (array_key_exists('receipt_number', $data) && $data['receipt_number'] !== null) $ftData['receipt_number'] = $data['receipt_number'];
            $ft->fill($ftData);

            $ft->save();

            // try to find corresponding cash transaction: match by same reference_type/reference_id and old amount
            $cash = CashTransaction::where('store_id', $storeId)
                ->where('reference_type', $ft->reference_type)
                ->where('reference_id', $ft->reference_id)
                ->where('amount', $oldAmount)
                ->first();

            // If reference_id is 0, we can also try to match using description or creation date if multiple transactions exist
            if (!$cash && $ft->reference_id == 0) {
                $cash = CashTransaction::where('store_id', $storeId)
                    ->where('reference_type', $ft->reference_type)
                    ->where('amount', $oldAmount)
                    ->where('created_by', $ft->created_by)
                    ->first();
            }

            if ($cash) {
                $cash->amount = $data['amount'] ?? $cash->amount;
                if (isset($data['description'])) $cash->description = $data['description'];
                if (isset($data['payment_date'])) $cash->payment_date = $data['payment_date'];
                if (isset($data['transaction_date'])) $cash->transaction_date = $data['transaction_date'];
                $cash->save();
            }

            // if this is an invoice payment, adjust the invoice paid/remaining amounts
            if (in_array($ft->reference_type, ['sales_invoice_payment', 'purchase_invoice_payment'])) {
                $delta = ($data['amount'] ?? $ft->amount) - $oldAmount;
                if ($delta !== 0) {
                    if ($ft->reference_type === 'sales_invoice_payment') {
                        $invoice = \App\Models\SalesInvoice::where('store_id', $storeId)->find($ft->reference_id);
                    } else {
                        $invoice = \App\Models\PurchaseInvoice::where('store_id', $storeId)->find($ft->reference_id);
                    }

                    if ($invoice) {
                        $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $delta;
                        $invoice->remaining_amount = max(0, ($invoice->total_amount ?? 0) - $invoice->paid_amount);
                        $invoice->save();
                    }
                }
            }

            // invalidate caches
            if ($ft->party_type === PartyType::CUSTOMER) {
                $this->cacheService->invalidateCustomerBalance($ft->party_id);
            } else {
                $this->cacheService->invalidateSupplierBalance($ft->party_id);
            }
            $this->cacheService->invalidateCashBalance($storeId);
        });
    }

    /**
     * Delete a direct payment and its associated cash transaction if found.
     */
    public function deleteDirectPayment(int $storeId, int $paymentId): void
    {
        DB::transaction(function () use ($storeId, $paymentId) {
            // First find the FinancialTransaction — the $paymentId is always the FT id
            $ft = FinancialTransaction::where('store_id', $storeId)->find($paymentId);

            // If it has a real Payment entity linked, delete that too
            $payment = null;
            if ($ft && $ft->reference_type === 'payment' && $ft->reference_id > 0) {
                $payment = Payment::where('store_id', $storeId)->find($ft->reference_id);
            }

            if ($payment) {
                // delete ALL financial transactions referencing this payment (payment or direct_payment)
                FinancialTransaction::where('store_id', $storeId)
                    ->whereIn('reference_type', ['payment', 'direct_payment'])
                    ->where('reference_id', $payment->id)
                    ->delete();

                // delete ALL cash transactions referencing this payment
                CashTransaction::where('store_id', $storeId)
                    ->whereIn('reference_type', ['payment', 'direct_payment'])
                    ->where('reference_id', $payment->id)
                    ->delete();

                $partyType = $payment->party_type;
                $partyId = $payment->party_id;

                $payment->delete();

                if ($partyType === PartyType::CUSTOMER) {
                    $this->cacheService->invalidateCustomerBalance($partyId);
                } else {
                    $this->cacheService->invalidateSupplierBalance($partyId);
                }
                $this->cacheService->invalidateCashBalance($storeId);

                return;
            }

            // Fallback: legacy record (direct_payment with reference_id = 0, or no Payment entity)
            if (!$ft) {
                $ft = FinancialTransaction::where('store_id', $storeId)
                    ->where('id', $paymentId)
                    ->firstOrFail();
            }

            // try to find corresponding cash transaction
            $cash = CashTransaction::where('store_id', $storeId)
                ->where('reference_type', $ft->reference_type)
                ->where('reference_id', $ft->reference_id)
                ->where('amount', $ft->amount)
                ->first();

            // fallback for cash transaction if reference_id is 0
            if (!$cash && $ft->reference_id == 0) {
                $cash = CashTransaction::where('store_id', $storeId)
                    ->whereIn('reference_type', ['payment', 'direct_payment'])
                    ->where('amount', $ft->amount)
                    ->where('created_by', $ft->created_by)
                    ->first();
            }

            $partyId = $ft->party_id;
            $partyType = $ft->party_type;

            // if invoice payment, roll back invoice paid amounts
            if ($ft->reference_type === 'sales_invoice_payment') {
                $invoice = \App\Models\SalesInvoice::where('store_id', $storeId)->find($ft->reference_id);
                if ($invoice) {
                    $invoice->paid_amount = max(0, ($invoice->paid_amount ?? 0) - $ft->amount);
                    $invoice->remaining_amount = min($invoice->total_amount ?? 0, ($invoice->remaining_amount ?? ($invoice->total_amount ?? 0)) + $ft->amount);
                    $invoice->save();
                }
            } elseif ($ft->reference_type === 'purchase_invoice_payment') {
                $invoice = \App\Models\PurchaseInvoice::where('store_id', $storeId)->find($ft->reference_id);
                if ($invoice) {
                    $invoice->paid_amount = max(0, ($invoice->paid_amount ?? 0) - $ft->amount);
                    $invoice->remaining_amount = min($invoice->total_amount ?? 0, ($invoice->remaining_amount ?? ($invoice->total_amount ?? 0)) + $ft->amount);
                    $invoice->save();
                }
            }

            $ft->delete();

            if ($cash) {
                $cash->delete();
            }

            if ($partyType === PartyType::CUSTOMER) {
                $this->cacheService->invalidateCustomerBalance($partyId);
            } else {
                $this->cacheService->invalidateSupplierBalance($partyId);
            }
            $this->cacheService->invalidateCashBalance($storeId);
        });
    }

    /**
     * دفع نقدي لمورد (بدون فاتورة).
     */
    public function payToSupplier(RecordPaymentDTO $dto): ?Payment
    {
        $createdPayment = null;

        DB::transaction(function () use ($dto, &$createdPayment) {

            $invoice = \App\Models\PurchaseInvoice::where('store_id', $dto->storeId)->find($dto->invoiceId);

            if ($dto->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
                ]);
            }

            if (($dto->invoiceId ?? 0) > 0) {
                $supplierId = $invoice->supplier_id;
            } else {
                if (!$dto->partyId) {
                    throw ValidationException::withMessages(['party_id' => 'يرجى تحديد المورد عند إنشاء قيد دفع مباشر.']);
                }
                $supplierId = $dto->partyId;
            }

            // reference_type/reference_id differ for invoice-linked vs direct payments
            if (($dto->invoiceId ?? 0) > 0) {
                $refType = 'purchase_invoice';
                $refId = $invoice->id;
                $description = $dto->notes ?? "دفع للفاتورة: {$invoice->invoice_number}";
            } else {
                $description = $dto->notes ?? "دفع نقدي مباشر للمورد: {$supplierId}";

                // create Payment entity for direct supplier payment
                $paymentNumber = $dto->receiptNumber;
                if (empty($paymentNumber)) {
                    $year = now()->year;
                    $last = Payment::withoutGlobalScopes()
                        ->where('store_id', $dto->storeId)
                        ->whereYear('created_at', $year)
                        ->count();
                    $paymentNumber = sprintf('PM-%d-%04d', $year, $last + 1);
                }

                $payment = Payment::create([
                    'store_id' => $dto->storeId,
                    'party_type' => PartyType::SUPPLIER,
                    'party_id' => $supplierId,
                    'amount' => $dto->amount,
                    'payment_number' => $paymentNumber,
                    'payment_date' => $dto->date ?? today(),
                    'description' => $dto->notes ?? null,
                    'receipt_number' => $dto->receiptNumber ?? null,
                    'created_by' => $dto->createdBy,
                ]);

                $createdPayment = $payment;
                $refType = 'payment';
                $refId = $payment->id;
            }

            // generate receipt number for supplier/direct payments if missing
            $receiptNumber = $dto->receiptNumber;
            if (empty($receiptNumber)) {
                $year = now()->year;
                $last = FinancialTransaction::withoutGlobalScopes()
                    ->where('store_id', $dto->storeId)
                    ->whereYear('created_at', $year)
                    ->whereNotNull('receipt_number')
                    ->count();
                $receiptNumber = sprintf('RC-%d-%04d', $year, $last + 1);
            }

            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::SUPPLIER,
                'party_id'       => $supplierId,
                'type'           => TransactionType::CREDIT,
                'amount'         => $dto->amount,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'description'    => $description,
                'receipt_number' => $receiptNumber,
                'created_by'     => $dto->createdBy,
            ]);

            // cash out
            CashTransaction::create([
                'store_id'         => $dto->storeId,
                'type'             => CashTransactionType::OUT,
                'amount'           => $dto->amount,
                'reference_type'   => $refType,
                'reference_id'     => $refId,
                'description'      => $description,
                'transaction_date' => $dto->date ?? today(),
                'created_by'       => $dto->createdBy,
            ]);

            if (($dto->invoiceId ?? 0) > 0) {
                // update invoice balance only for invoice-linked payments
                $invoice->paid_amount = ($invoice->paid_amount ?? 0) + $dto->amount;
                $invoice->remaining_amount = max(0, ($invoice->remaining_amount ?? ($invoice->total_amount - ($invoice->paid_amount ?? 0))) - $dto->amount);
                $invoice->save();
            }

            $this->cacheService->invalidateSupplierBalance($supplierId);
            $this->cacheService->invalidateCashBalance($dto->storeId);
        });

        return $createdPayment;
    }
}
