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
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * تحصيل نقدي من عميل (بدون فاتورة).
     */
    public function collectFromCustomer(RecordPaymentDTO $dto): void
    {
        DB::transaction(function () use ($dto) {

            $customer = Customer::findOrFail($dto->partyId);

            if ($dto->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
                ]);
            }

            // قيد credit للعميل (يُقلل دينه)
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::CUSTOMER,
                'party_id'       => $dto->partyId,
                'type'           => TransactionType::CREDIT,
                'amount'         => $dto->amount,
                'reference_type' => 'direct_payment',
                'reference_id'   => 0,
                'description'    => $dto->notes ?? "تحصيل نقدي من العميل: {$customer->name}",
                'created_by'     => $dto->createdBy,
            ]);

            // قيد نقدي وارد
            CashTransaction::create([
                'store_id'         => $dto->storeId,
                'type'             => CashTransactionType::IN,
                'amount'           => $dto->amount,
                'reference_type'   => 'direct_payment',
                'reference_id'     => $dto->partyId,
                'description'      => "تحصيل من: {$customer->name}",
                'transaction_date' => $dto->date ?? today(),
                'created_by'       => $dto->createdBy,
            ]);
        });
    }

    /**
     * دفع نقدي لمورد (بدون فاتورة).
     */
    public function payToSupplier(RecordPaymentDTO $dto): void
    {
        DB::transaction(function () use ($dto) {

            $supplier = Supplier::findOrFail($dto->partyId);

            if ($dto->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يجب أن يكون أكبر من صفر.',
                ]);
            }

            // قيد credit للمورد (يُقلل ما علينا له)
            FinancialTransaction::create([
                'store_id'       => $dto->storeId,
                'party_type'     => PartyType::SUPPLIER,
                'party_id'       => $dto->partyId,
                'type'           => TransactionType::CREDIT,
                'amount'         => $dto->amount,
                'reference_type' => 'direct_payment',
                'reference_id'   => 0,
                'description'    => $dto->notes ?? "دفع نقدي للمورد: {$supplier->name}",
                'created_by'     => $dto->createdBy,
            ]);

            // قيد نقدي صادر
            CashTransaction::create([
                'store_id'         => $dto->storeId,
                'type'             => CashTransactionType::OUT,
                'amount'           => $dto->amount,
                'reference_type'   => 'direct_payment',
                'reference_id'     => $dto->partyId,
                'description'      => "دفع لـ: {$supplier->name}",
                'transaction_date' => $dto->date ?? today(),
                'created_by'       => $dto->createdBy,
            ]);
        });
    }
}
