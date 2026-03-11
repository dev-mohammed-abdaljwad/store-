<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PurchaseInvoice\StorePurchaseInvoiceRequest;
use App\Http\Requests\Api\V1\SalesInvoice\CancelInvoiceRequest;
use App\Domain\Store\DTOs\CreatePurchaseInvoiceDTO;
use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceController extends Controller
{
    public function __construct(private PurchaseInvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10);

        $from = $request->from ? $request->from . ' 00:00:00' : null;
        $to = $request->to ? $request->to . ' 23:59:59' : null;

        $invoices = PurchaseInvoice::query()
            ->select([
                'id',
                'store_id',
                'invoice_number',
                'supplier_id',
                'total_amount',
                'paid_amount',
                'remaining_amount',
                'status',
                'notes',
                'created_by',
                'created_at',
            ])
            ->with('supplier:id,name,phone')
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($from,                 fn($q) => $q->where('created_at', '>=', $from))
            ->when($to,                   fn($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($invoices);
    }

    public function store(StorePurchaseInvoiceRequest $request): JsonResponse
    {
        $dto = CreatePurchaseInvoiceDTO::fromArray(
            data: $request->validated(),
            storeId: Auth::user()->getStoreId(),
            createdBy: Auth::id(),
        );

        $invoice = $this->invoiceService->create($dto);

        return response()->json([
            'message' => 'تم إنشاء فاتورة الشراء بنجاح.',
            'invoice' => $invoice,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = PurchaseInvoice::with('items.product', 'items.variant', 'supplier', 'createdBy')
            ->findOrFail($id);

        return response()->json($invoice);
    }

    public function cancel(CancelInvoiceRequest $request, int $id): JsonResponse
    {
        $dto = CancelInvoiceDTO::fromArray(
            data: $request->validated(),
            invoiceId: $id,
            storeId: Auth::user()->getStoreId(),
            cancelledBy: Auth::id(),
        );

        $invoice = $this->invoiceService->cancel($dto);

        return response()->json([
            'message' => 'تم إلغاء فاتورة الشراء.',
            'invoice' => $invoice,
        ]);
    }

    public function getItems(int $id): JsonResponse
    {
        $invoice = PurchaseInvoice::with('items.variant.product')
            ->where('store_id', Auth::user()->getStoreId())
            ->findOrFail($id);

        $items = $invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => $item->received_quantity,
                'unit_price' => $item->unit_price,
                'total_amount' => $item->total_price,
            ];
        })->values();

        return response()->json([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'supplier_id' => $invoice->supplier_id,
            'items' => $items,
        ]);
    }
}
