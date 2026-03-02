<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10);

        $products = Product::query()
            ->with('category:id,name')
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('sku', 'like', '%' . $request->search . '%');
                })
            )
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $productIds = $products->getCollection()->pluck('id')->all();

        $stockTotals = empty($productIds)
            ? collect()
            : DB::table('stock_movements')
                ->selectRaw('product_id')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END), 0) AS stock_in")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END), 0) AS stock_out")
                ->where('store_id', Auth::user()->getStoreId())
                ->whereIn('product_id', $productIds)
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($stockTotals) {
                $stock = $stockTotals->get($product->id);
                $stockIn = (float) ($stock->stock_in ?? 0);
                $stockOut = (float) ($stock->stock_out ?? 0);
                $currentStock = round($stockIn - $stockOut, 3);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'unit' => $product->unit,
                    'sale_price' => $product->sale_price,
                    'current_stock' => $currentStock,
                    'low_stock_threshold' => $product->low_stock_threshold,
                    'is_low_stock' => $product->low_stock_threshold > 0 && $currentStock <= $product->low_stock_threshold,
                ];
            })
        );

        return response()->json($products);
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

    // ── Categories ───────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 25);

        $categories = Category::query()
            ->select(['id', 'store_id', 'name', 'products_count', 'created_at', 'updated_at'])
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($categories);
    }

    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'store_id' => Auth::user()->getStoreId(),
            'name'     => $request->name,
        ]);

        return response()->json([
            'message'  => 'تم إضافة التصنيف.',
            'category' => $category,
        ], 201);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $this->productService->deleteCategory($id, Auth::user()->getStoreId());

        return response()->json(['message' => 'تم حذف التصنيف.']);
    }
}
