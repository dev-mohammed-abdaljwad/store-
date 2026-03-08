<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    // GET /api/store/inventory
    public function inventory(): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        // Get the latest purchase item id per variant for this store to resolve supplier name.
        $latestPurchaseItemIds = PurchaseInvoiceItem::query()
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_items.invoice_id')
            ->where('purchase_invoices.store_id', $storeId)
            ->whereNull('purchase_invoices.deleted_at')
            ->groupBy('purchase_invoice_items.variant_id')
            ->selectRaw('MAX(purchase_invoice_items.id) as id');

        $suppliersByVariant = PurchaseInvoiceItem::query()
            ->joinSub($latestPurchaseItemIds, 'latest_items', function ($join) {
                $join->on('purchase_invoice_items.id', '=', 'latest_items.id');
            })
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_items.invoice_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->where('purchase_invoices.store_id', $storeId)
            ->select([
                'purchase_invoice_items.variant_id',
                'suppliers.name as supplier_name',
            ])
            ->get()
            ->pluck('supplier_name', 'variant_id');

        $variants = ProductVariant::query()
            ->with(['product.category', 'product'])
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderBy('name')
            ->get()
            ->map(function (ProductVariant $variant) use ($suppliersByVariant) {
                $stock = $variant->current_stock;

                return [
                    'variant_id'     => $variant->id,
                    'product_name'   => $variant->product?->name,
                    'variant_name'   => $variant->name,
                    'category'       => $variant->product?->category?->name ?? '—',
                    'supplier_name'  => $suppliersByVariant[$variant->id] ?? null,
                    'sale_price'     => $variant->sale_price,
                    'purchase_price' => $variant->purchase_price,
                    'current_stock'  => $stock,
                    'status'         => $stock <= 0
                        ? 'out'
                        : ($variant->isLowStock() ? 'low' : 'available'),
                ];
            })
            ->values();

        return response()->json($variants);
    }
}
