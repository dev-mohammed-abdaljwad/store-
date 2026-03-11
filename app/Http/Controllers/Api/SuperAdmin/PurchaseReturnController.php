<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Store\DTOs\CreatePurchaseReturnDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PurchaseReturn\StorePurchaseReturnRequest;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseReturnService $purchaseReturnService) {}

    public function index(Request $request): JsonResponse
    {
        $returns = $this->purchaseReturnService->list(
            storeId: Auth::user()->getStoreId(),
            filters: $request->only(['search', 'supplier_id', 'from', 'to']),
        );

        return response()->json($returns);
    }

    public function store(StorePurchaseReturnRequest $request): JsonResponse
    {
        $dto = CreatePurchaseReturnDTO::fromArray(
            data: $request->validated(),
            storeId: Auth::user()->getStoreId(),
            createdBy: Auth::id(),
        );

        $return = $this->purchaseReturnService->create($dto);

        return response()->json([
            'message' => 'تم إنشاء مرتجع الشراء بنجاح.',
            'return' => $return,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $return = PurchaseReturn::with('items.variant.product.category', 'supplier', 'invoice', 'createdBy')
            ->where('store_id', Auth::user()->getStoreId())
            ->findOrFail($id);

        return response()->json($return);
    }
}
