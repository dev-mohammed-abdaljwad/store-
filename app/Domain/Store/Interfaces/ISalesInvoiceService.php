<?php
namespace App\Domain\Store\Interfaces;

use App\Domain\Store\DTOs\CreateSalesInvoiceDTO;
use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Models\SalesInvoice;
interface ISalesInvoiceService
{
     /**
     * إنشاء فاتورة بيع.
     *
     * يُنفّذ في DB::transaction واحدة:
     * 1. التحقق من توافر المخزون لكل منتج
     * 2. حفظ الفاتورة
     * 3. خصم المخزون (stock_movements)
     * 4. قيد مالي مدين على العميل (financial_transactions)
     * 5. قيد نقدي إذا paid_amount > 0 (cash_transactions)
     */
    public function createInvoice(CreateSalesInvoiceDTO $dto): SalesInvoice;
     
    /**
     * إلغاء فاتورة بيع.
     *
     * يُنفّذ في DB::transaction واحدة:
     * 1. تغيير status إلى cancelled
     * 2. عكس حركات المخزون
     * 3. عكس القيد المالي
     * 4. عكس القيد النقدي (إن وجد)
     * 5.
     * */
    public function cancelInvoice(CancelInvoiceDTO $dto): SalesInvoice;    
}
