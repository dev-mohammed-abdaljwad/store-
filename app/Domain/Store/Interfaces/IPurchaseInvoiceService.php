<?php
namespace App\Domain\Store\Interfaces;

use App\Domain\Store\DTOs\CreatePurchaseInvoiceDTO;
use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Models\PurchaseInvoice;
interface IPurchaseInvoiceService
{
     /**
     * إنشاء فاتورة شراء.
     *
     * يُنفّذ في DB::transaction واحدة:
     * 1. حفظ الفاتورة
     * 2. إضافة حركات المخزون (stock_movements)
     * 3. قيد مالي دائن على المورد (financial_transactions)
     * 4. قيد نقدي إذا paid_amount > 0 (cash_transactions)
     */
    public function createInvoice(CreatePurchaseInvoiceDTO $dto): PurchaseInvoice;
     
    /**
     * إلغاء فاتورة شراء.
     *
     * يُنفّذ في DB::transaction واحدة:
     * 1. تغيير status إلى cancelled
     * 2. عكس حركات المخزون
     * 3. عكس القيد المالي
     * 4. عكس القيد النقدي (إن وجد)
     * 5.
     * */
    public function cancelInvoice(CancelInvoiceDTO $dto): PurchaseInvoice;    
}
