<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesInvoice\StoreSalesInvoiceRequest;
use App\Http\Requests\Api\V1\SalesInvoice\CancelInvoiceRequest;
use App\Domain\Store\DTOs\CreateSalesInvoiceDTO;
use App\Domain\Store\DTOs\CancelInvoiceDTO;
use App\Models\SalesInvoice;
use App\Services\SalesInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesInvoiceController extends Controller
{
    public function __construct(private SalesInvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request, 10);

        $from = $request->from ? $request->from . ' 00:00:00' : null;
        $to = $request->to ? $request->to . ' 23:59:59' : null;

        $invoices = SalesInvoice::query()
            ->select([
                'id',
                'store_id',
                'invoice_number',
                'customer_id',
                'total_amount',
                'paid_amount',
                'remaining_amount',
                'status',
                'notes',
                'created_by',
                'created_at',
            ])
            ->with('customer:id,name,phone')
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($query) use ($request) {
                    $search = '%' . $request->search . '%';

                    $query->where('invoice_number', 'like', $search)
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', $search)
                                ->orWhere('phone', 'like', $search);
                        });
                })
            )
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($from,                 fn($q) => $q->where('created_at', '>=', $from))
            ->when($to,                   fn($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($invoices);
    }

    public function store(StoreSalesInvoiceRequest $request): JsonResponse
    {
        $dto = CreateSalesInvoiceDTO::fromArray(
            data: $request->validated(),
            storeId: Auth::user()->getStoreId(),
            createdBy: Auth::id(),
        );

        $invoice = $this->invoiceService->create($dto);

        return response()->json([
            'message' => 'تم إنشاء فاتورة البيع بنجاح.',
            'invoice' => $invoice,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = SalesInvoice::with('items.product', 'customer', 'createdBy')
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
            'message' => 'تم إلغاء الفاتورة.',
            'invoice' => $invoice,
        ]);
    }
}
