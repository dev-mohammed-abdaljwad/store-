<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachmentService) {}

    /**
     * POST /api/store/purchase-invoices/{id}/attachment
     * رفع مرفق
     */
    public function upload(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'attachment' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'attachment.required' => 'يرجى اختيار ملف.',
            'attachment.file'     => 'الملف غير صالح.',
            'attachment.max'      => 'حجم الملف يتجاوز 5 ميجابايت.',
            'attachment.mimes'    => 'الصيغة غير مدعومة. المسموح: JPG, PNG, PDF.',
        ]);

        $invoice = $this->attachmentService->upload(
            invoiceId: $id,
            storeId:   Auth::user()->getStoreId(),
            file:      $request->file('attachment'),
        );

        return response()->json([
            'message'       => 'تم رفع المرفق بنجاح.',
            'attachment'    => [
                'name' => $invoice->attachment_original_name,
                'url'  => route('store.purchase-invoices.attachment.view', ['id' => $id]),
            ],
        ]);
    }

  

    public function view(int $id): mixed
    {
        $data = $this->attachmentService->download(
            invoiceId: $id,
            storeId:   Auth::user()->getStoreId(),
        );

        return response()->file(
            Storage::disk('private')->path($data['path']),
            [
                'Content-Type'        => $data['mime'],
                'Content-Disposition' => 'inline; filename="' . $data['original_name'] . '"',
            ],
        );
    }

    /**
     * DELETE /api/store/purchase-invoices/{id}/attachment
     * حذف المرفق
     */
    public function delete(int $id): JsonResponse
    {
        $this->attachmentService->delete(
            invoiceId: $id,
            storeId:   Auth::user()->getStoreId(),
        );

        return response()->json(['message' => 'تم حذف المرفق.']);
    }
}