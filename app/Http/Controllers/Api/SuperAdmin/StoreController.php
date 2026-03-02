<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;

use App\Http\Requests\Api\V1\Store\CreateStoreRequest;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    private StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 20);

        $stores = Store::query()
            ->when($request->filled('search'), fn($q) => $q->where(function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('owner_name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn(Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'email' => $store->email,
                'owner_name' => $store->owner_name,
                'is_active' => $store->is_active,
                'created_at' => $store->created_at?->format('Y-m-d'),
            ])
            ->withQueryString();

        return response()->json($stores);
    }

    public function store(CreateStoreRequest $request): JsonResponse
    {
        $store = $this->storeService->createStore($request->validated());

        return response()->json([
            'message' => 'تم إنشاء المتجر بنجاح.',
            'store'   => $store,
        ], 201);
    }

    public function activate(int $id): JsonResponse
    {
        $this->storeService->activate($id);

        return response()->json(['message' => 'تم تفعيل المتجر بنجاح']);
    }

    public function deactivate(int $id): JsonResponse
    {
        $this->storeService->deactivate($id);

        return response()->json(['message' => 'تم تعطيل المتجر بنجاح']);
    }
}
