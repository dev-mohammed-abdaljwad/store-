<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\RecordPaymentRequest;
use App\Domain\Store\DTOs\RecordPaymentDTO;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function collectFromCustomer(RecordPaymentRequest $request): JsonResponse
    {
        $dto = RecordPaymentDTO::fromArray(
            data: $request->validated(),
            storeId: auth()->user()->getStoreId(),
            createdBy: auth()->id(),
        );

        $this->paymentService->collectFromCustomer($dto);

        return response()->json(['message' => 'تم تسجيل التحصيل بنجاح.']);
    }

    public function payToSupplier(RecordPaymentRequest $request): JsonResponse
    {
        $dto = RecordPaymentDTO::fromArray(
            data: $request->validated(),
            storeId: auth()->user()->getStoreId(),
            createdBy: auth()->id(),
        );

        $this->paymentService->payToSupplier($dto);

        return response()->json(['message' => 'تم تسجيل الدفعة بنجاح.']);
    }
}
