<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Supplier\StoreSupplierRequest;
use App\Http\Requests\Api\V1\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $supplierService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10);
        $storeId = Auth::user()->getStoreId();

        $suppliers = Supplier::query()
            ->select(['suppliers.id', 'suppliers.name', 'suppliers.phone'])
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('suppliers.name', 'like', '%' . $request->search . '%')
                          ->orWhere('suppliers.phone', 'like', '%' . $request->search . '%');
                })
            )
            ->orderBy('suppliers.name')
            ->paginate($perPage)
            ->withQueryString();

        $supplierIds = $suppliers->getCollection()->pluck('id')->all();

        $balances = empty($supplierIds)
            ? collect()
            : DB::table('financial_transactions')
                ->selectRaw('party_id')
                ->selectRaw("SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END) AS balance")
                ->where('store_id', $storeId)
                ->where('party_type', 'supplier')
                ->whereIntegerInRaw('party_id', $supplierIds)
                ->groupBy('party_id')
                ->pluck('balance', 'party_id');

        $suppliers->setCollection(
            $suppliers->getCollection()->map(function (Supplier $supplier) use ($balances) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone,
                    'balance' => round((float) ($balances[$supplier->id] ?? 0), 2),
                ];
            })
        );

        return response()->json($suppliers);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->create(
            $request->validated(),
            Auth::user()->getStoreId()
        );

        return response()->json([
            'message'  => 'تم إضافة المورد بنجاح.',
            'supplier' => $supplier,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        return response()->json([
            'supplier' => $supplier,
            'balance'  => $supplier->balance,
        ]);
    }

    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        $supplier = $this->supplierService->update($id, $request->validated());

        return response()->json([
            'message'  => 'تم تعديل بيانات المورد.',
            'supplier' => $supplier,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->supplierService->delete($id, (string) Auth::user()->getStoreId());

        return response()->json(['message' => 'تم حذف المورد.']);
    }

    public function statement(int $id, Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 20);

        $statement = $this->supplierService->getStatement(
            supplierId: $id,
            storeId: (string) Auth::user()->getStoreId(),
            from: $request->from,
            to: $request->to,
            perPage: $perPage,
        );

        return response()->json($statement);
    }
}
