<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\StoreVariantRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private CacheService $cacheService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10, 10);
        $withTotal = $request->boolean('with_total', false);
        $storeId = Auth::user()->getStoreId();

        $productsQuery = Product::query()
            ->with(['category:id,name', 'variants'])
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                        ->orWhereHas('allVariants', fn($v) => $v->where('sku', 'like', '%' . $request->search . '%'));
                })
            )
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name');

        $products = ($withTotal ? $productsQuery->paginate($perPage) : $productsQuery->simplePaginate($perPage))
            ->withQueryString();

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'variants' => $product->variants->map(fn($variant) => [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'purchase_price' => $variant->purchase_price,
                        'sale_price' => $variant->sale_price,
                        'current_stock' => $variant->current_stock,
                        'low_stock_threshold' => $variant->low_stock_threshold,
                        'is_low_stock' => $variant->isLowStock(),
                    ])->values(),
                ];
            })
        );

        $payload = $products->toArray();
        $payload['products_count'] = $this->cacheService->getProductsCount($storeId);

        return response()->json($payload);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $request->validated(),
            Auth::user()->getStoreId()
        );

        return response()->json([
            'message' => 'تم إضافة المنتج بنجاح.',
            'product' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productService->update($id, $request->validated());

        return response()->json([
            'message' => 'تم تعديل المنتج.',
            'product' => $product,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->productService->delete($id);

        return response()->json(['message' => 'تم حذف المنتج.']);
    }

    public function storeVariant(StoreVariantRequest $request, int $productId): JsonResponse
    {
        $variant = $this->productService->addVariant(
            productId: $productId,
            data: $request->validated(),
            storeId: Auth::user()->getStoreId(),
        );

        return response()->json([
            'message' => 'تم إضافة الحجم بنجاح.',
            'variant' => $variant,
        ], 201);
    }

    public function updateVariant(StoreVariantRequest $request, int $productId, int $variantId): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();
        Product::where('store_id', $storeId)->findOrFail($productId);

        $variant = $this->productService->updateVariant(
            variantId: $variantId,
            data: $request->validated(),
            storeId: $storeId,
        );

        return response()->json([
            'message' => 'تم تعديل الحجم.',
            'variant' => $variant,
        ]);
    }

    public function destroyVariant(int $productId, int $variantId): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();
        Product::where('store_id', $storeId)->findOrFail($productId);

        $this->productService->deleteVariant($variantId, $storeId);

        return response()->json(['message' => 'تم حذف الحجم.']);
    }

    public function dropdown(): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        return response()->json([
            'items' => $this->productService->listForDropdown($storeId),
        ]);
    }

    // ── Categories ───────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 25);
        $storeId = Auth::user()->getStoreId();

        if (! $request->filled('search')) {
            $allCategories = collect($this->cacheService->getCategories($storeId));
            $page = max((int) $request->query('page', 1), 1);
            $total = $allCategories->count();
            $items = $allCategories->forPage($page, $perPage)->values();

            $paginator = new LengthAwarePaginator(
                items: $items,
                total: $total,
                perPage: $perPage,
                currentPage: $page,
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );

            return response()->json($paginator);
        }

        $categories = Category::query()
            ->select(['id', 'store_id', 'name', 'products_count', 'created_at', 'updated_at'])
            ->withCount(['products as products_count' => fn($q) => $q->whereNull('products.deleted_at')])
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($categories);
    }

    public function categoriesSummary(): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        return response()->json([
            'categories' => $this->cacheService->getCategories($storeId),
        ]);
    }

    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        $category = Category::create([
            'store_id' => $storeId,
            'name'     => $request->name,
        ]);

        $this->cacheService->invalidateCategories($storeId);

        return response()->json([
            'message'  => 'تم إضافة التصنيف.',
            'category' => $category,
        ], 201);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        $this->productService->deleteCategory($id, $storeId);
        $this->cacheService->invalidateCategories($storeId);

        return response()->json(['message' => 'تم حذف التصنيف.']);
    }
}
