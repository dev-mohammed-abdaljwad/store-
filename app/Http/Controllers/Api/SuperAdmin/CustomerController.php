<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customerService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10);
        $storeId = Auth::user()->getStoreId();

        $customers = Customer::query()
            ->select(['customers.id', 'customers.name', 'customers.phone'])
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('customers.name', 'like', '%' . $request->search . '%')
                          ->orWhere('customers.phone', 'like', '%' . $request->search . '%');
                })
            )
            ->orderBy('customers.name')
            ->paginate($perPage)
            ->withQueryString();

        $customerIds = $customers->getCollection()->pluck('id')->all();

        $balances = empty($customerIds)
            ? collect()
            : DB::table('financial_transactions')
                ->selectRaw('party_id')
                ->selectRaw("SUM(CASE WHEN type = 'debit' THEN amount ELSE -amount END) AS balance")
                ->where('store_id', $storeId)
                ->where('party_type', 'customer')
                ->whereIntegerInRaw('party_id', $customerIds)
                ->groupBy('party_id')
                ->pluck('balance', 'party_id');

        $customers->setCollection(
            $customers->getCollection()->map(function (Customer $customer) use ($balances) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'balance' => round((float) ($balances[$customer->id] ?? 0), 2),
                ];
            })
        );

        return response()->json($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $storeId  = Auth::user()->getStoreId();
        $customer = $this->customerService->create($request->validated(), $storeId);

        return response()->json([
            'message'  => 'تم إضافة العميل بنجاح.',
            'customer' => $customer,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        return response()->json([
            'customer' => $customer,
            'balance'  => $customer->balance,
        ]);
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $storeId  = Auth::user()->getStoreId();
        $customer = $this->customerService->update($id, $request->validated(), $storeId);

        return response()->json([
            'message'  => 'تم تعديل بيانات العميل.',
            'customer' => $customer,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->customerService->delete($id, Auth::user()->getStoreId());

        return response()->json(['message' => 'تم حذف العميل.']);
    }

    public function statement(int $id, Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 20);

        $statement = $this->customerService->getStatement(
            customerId: $id,
            storeId: Auth::user()->getStoreId(),
            from: $request->from,
            to: $request->to,
            perPage: $perPage,
        );

        return response()->json($statement);
    }
}
