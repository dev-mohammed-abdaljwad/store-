<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\RecordPaymentRequest;
use App\Domain\Store\DTOs\RecordPaymentDTO;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domain\Store\Enums\PartyType;

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

        $payment = $this->paymentService->collectFromCustomer($dto);

        return response()->json([
            'message' => 'تم تسجيل التحصيل بنجاح.',
            'payment' => $payment ? $payment->toArray() : null,
        ]);
    }

    public function payToSupplier(RecordPaymentRequest $request): JsonResponse
    {
        $dto = RecordPaymentDTO::fromArray(
            data: $request->validated(),
            storeId: auth()->user()->getStoreId(),
            createdBy: auth()->id(),
        );

        $payment = $this->paymentService->payToSupplier($dto);

        return response()->json([
            'message' => 'تم تسجيل الدفعة بنجاح.',
            'payment' => $payment ? $payment->toArray() : null,
        ]);
    }

    public function listCustomerPayments(Request $request, int $customerId): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listDirectPayments(
            auth()->user()->getStoreId(),
            PartyType::CUSTOMER,
            $customerId,
            $perPage
        );

        return response()->json($page->toArray());
    }

    public function listAllPayments(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listAllPayments(auth()->user()->getStoreId(), $perPage, $request->query('page', 1));

        return response()->json($page->toArray());
    }

    public function listAllCustomerPayments(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listPaymentsByPartyType(auth()->user()->getStoreId(), \App\Domain\Store\Enums\PartyType::CUSTOMER, $perPage, $request->query('page', 1));

        return response()->json($page->toArray());
    }

    public function listAllSupplierPayments(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listPaymentsByPartyType(auth()->user()->getStoreId(), \App\Domain\Store\Enums\PartyType::SUPPLIER, $perPage, $request->query('page', 1));

        return response()->json($page->toArray());
    }

    public function listSupplierPayments(Request $request, int $supplierId): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        $page = $this->paymentService->listDirectPayments(
            auth()->user()->getStoreId(),
            PartyType::SUPPLIER,
            $supplierId,
            $perPage
        );

        return response()->json($page->toArray());
    }

    public function updatePayment(Request $request, int $paymentId): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'description' => 'nullable|string',
            'receipt_number' => 'nullable|string',
            'transaction_date' => 'nullable|date',
        ]);

        $this->paymentService->updateDirectPayment(auth()->user()->getStoreId(), $paymentId, $data);

        return response()->json(['message' => 'تم تحديث الفاتورة بنجاح.']);
    }

    public function deletePayment(int $paymentId): JsonResponse
    {
        $this->paymentService->deleteDirectPayment(auth()->user()->getStoreId(), $paymentId);

        return response()->json(['message' => 'تم حذف الفاتورة بنجاح.']);
    }
}
