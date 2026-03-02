<?php
namespace App\Domain\Store\Interfaces;
use App\Domain\Store\DTOs\RecordPaymentDTO;
interface IPaymentService
{
    /**
     * تحصيل نقدي من عميل (بدون فاتورة).
     * → financial_transaction (credit للعميل)
     * → cash_transaction (in)
     */
    public function collectFromCustomer(RecordPaymentDTO $dto): void;

    /**
     * دفع نقدي لمورد (بدون فاتورة).
     * → financial_transaction (credit للمورد)
     * → cash_transaction (out)
     */
    public function payToSupplier(RecordPaymentDTO $dto): void;
}
