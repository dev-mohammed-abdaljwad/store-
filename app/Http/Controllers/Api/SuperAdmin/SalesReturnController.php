<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Domain\Store\DTOs\CreateSalesReturnDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesReturn\StoreSalesReturnRequest;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesReturnController extends Controller
{
    public function __construct(private SalesReturnService $salesReturnService) {}

    public function index(Request $request): JsonResponse
    {
        $returns = $this->salesReturnService->list(
            storeId: Auth::user()->getStoreId(),
            filters: $request->only(['search', 'customer_id', 'from', 'to']),
        );

        return response()->json($returns);
    }

    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $dto = CreateSalesReturnDTO::fromArray(
            data: $request->validated(),
            storeId: Auth::user()->getStoreId(),
            createdBy: Auth::id(),
        );

        $return = $this->salesReturnService->create($dto);

        return response()->json([
            'message' => 'تم إنشاء مرتجع البيع بنجاح.',
            'return' => $return,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $return = SalesReturn::with('items.variant.product.category', 'customer', 'invoice', 'createdBy')
            ->where('store_id', Auth::user()->getStoreId())
            ->findOrFail($id);

        return response()->json($return);
    }
}
