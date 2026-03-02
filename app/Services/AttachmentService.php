<?php
namespace App\Services;

use App\Models\PurchaseInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    // الأنواع المسموح بها
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const MAX_SIZE_KB   = 5120; // 5MB

    /**
     * رفع مرفق لفاتورة شراء.
     */
    public function upload(int $invoiceId, int $storeId, UploadedFile $file): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::where('store_id', $storeId)->findOrFail($invoiceId);

        // ── التحقق من نوع الملف ───────────────────────────────────
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            throw ValidationException::withMessages([
                'attachment' => 'نوع الملف غير مدعوم. المسموح: JPG, PNG, PDF فقط.',
            ]);
        }

        // ── التحقق من الحجم ───────────────────────────────────────
        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            throw ValidationException::withMessages([
                'attachment' => 'حجم الملف يتجاوز 5 ميجابايت.',
            ]);
        }

        // ── حذف المرفق القديم لو موجود ───────────────────────────
        if ($invoice->attachment_path) {
            Storage::disk('private')->delete($invoice->attachment_path);
        }

        // ── حفظ الملف الجديد ──────────────────────────────────────
        // المسار: purchase-attachments/{store_id}/{invoice_id}_{timestamp}.{ext}
        $extension = $file->getClientOriginalExtension();
        $fileName  = "{$invoiceId}_" . time() . ".{$extension}";
        $path      = "purchase-attachments/{$storeId}/{$fileName}";

        Storage::disk('private')->putFileAs(
            "purchase-attachments/{$storeId}",
            $file,
            $fileName
        );

        // ── تحديث بيانات الفاتورة ─────────────────────────────────
        $invoice->update([
            'attachment_path'          => $path,
            'attachment_original_name' => $file->getClientOriginalName(),
        ]);

        return $invoice->fresh();
    }

    /**
     * عرض / تحميل المرفق.
     * بترجع StreamedResponse لعرض الملف مباشرة.
     */
    public function download(int $invoiceId, int $storeId): array
    {
        $invoice = PurchaseInvoice::where('store_id', $storeId)->findOrFail($invoiceId);

        if (! $invoice->attachment_path) {
            throw ValidationException::withMessages([
                'attachment' => 'لا يوجد مرفق لهذه الفاتورة.',
            ]);
        }

        if (! Storage::disk('private')->exists($invoice->attachment_path)) {
            throw ValidationException::withMessages([
                'attachment' => 'الملف غير موجود. يرجى رفعه مرة أخرى.',
            ]);
        }

        return [
            'path'          => $invoice->attachment_path,
            'original_name' => $invoice->attachment_original_name,
            'mime'          => File::mimeType(Storage::disk('private')->path($invoice->attachment_path)) ?: 'application/octet-stream',
        ];
    }

    /**
     * حذف المرفق.
     */
    public function delete(int $invoiceId, int $storeId): void
    {
        $invoice = PurchaseInvoice::where('store_id', $storeId)->findOrFail($invoiceId);

        if (! $invoice->attachment_path) {
            throw ValidationException::withMessages([
                'attachment' => 'لا يوجد مرفق لحذفه.',
            ]);
        }

        // حذف الملف من الـ storage
        Storage::disk('private')->delete($invoice->attachment_path);

        // مسح المسار من قاعدة البيانات
        $invoice->update([
            'attachment_path'          => null,
            'attachment_original_name' => null,
        ]);
    }
}