<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SalesInvoice\StoreSalesInvoiceRequest;
use App\Http\Requests\Api\V1\SalesInvoice\UpdateSalesInvoiceRequest;
use App\Http\Requests\Api\V1\SalesInvoice\CancelInvoiceRequest;
use App\Domain\Store\DTOs\CreateSalesInvoiceDTO;
use App\Domain\Store\DTOs\UpdateSalesInvoiceDTO;
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

        $from = $request->from ? $request->from : null;
        $to = $request->to ? $request->to : null;

        $invoices = SalesInvoice::query()
            ->select([
                'id',
                'store_id',
                'invoice_number',
                'invoice_date',
                'customer_id',
                'total_amount',
                'discount_amount',
                'net_amount',
                'paid_amount',
                'remaining_amount',
                'status',
                'notes',
                'sales_rep_name',
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
            ->when($from,                 fn($q) => $q->where('invoice_date', '>=', $from))
            ->when($to,                   fn($q) => $q->where('invoice_date', '<=', $to))
            ->orderByDesc('invoice_date')
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
        $deficits = $this->invoiceService->getInvoiceDeficits($invoice);

        return response()->json([
            'message' => 'تم إنشاء فاتورة البيع بنجاح.',
            'invoice' => $invoice,
            'deficits' => $deficits,
        ], 201);
    }

    public function update(UpdateSalesInvoiceRequest $request, int $id): JsonResponse
    {
        $dto = UpdateSalesInvoiceDTO::fromArray(
            data: $request->validated(),
            invoiceId: $id,
            storeId: Auth::user()->getStoreId(),
            updatedBy: Auth::id(),
        );

        $invoice = $this->invoiceService->update($dto);
        $deficits = $this->invoiceService->getInvoiceDeficits($invoice);

        return response()->json([
            'message' => 'تم تعديل فاتورة البيع بنجاح.',
            'invoice' => $invoice,
            'deficits' => $deficits,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = SalesInvoice::with('items.variant.product.category', 'customer', 'createdBy')
            ->findOrFail($id);

        $items = $invoice->items->map(function ($item) {
            $category = $item->variant?->product?->category;

            return [
                'id'            => $item->id,
                'variant_id'    => $item->variant_id,
                'product_name'  => $item->product_name,
                'variant_name'  => $item->variant_name,
                'category'      => $category?->name ?? '',
                'category_id'   => $category?->id,
                'quantity'      => $item->quantity,
                'unit_price'    => $item->unit_price,
                'total_amount'  => $item->total_price,
            ];
        })->values();

        $summaryByCategory = $items
            ->groupBy(fn($item) => $item['category_id'] ?? 0)
            ->map(function ($group) {
                return [
                    'category_id'   => $group->first()['category_id'],
                    'category'      => $group->first()['category'] ?: 'بدون تصنيف',
                    'items_count'   => $group->count(),
                    'total_amount'  => round((float) $group->sum('total_amount'), 2),
                    'total_quantity'=> round((float) $group->sum('quantity'), 3),
                ];
            })
            ->values();

        return response()->json([
            'id'               => $invoice->id,
            'invoice_number'   => $invoice->invoice_number,
            'invoice_date'     => $invoice->invoice_date,
            'total_amount'     => $invoice->total_amount,
            'discount_amount'  => $invoice->discount_amount,
            'net_amount'       => $invoice->net_amount,
            'paid_amount'      => $invoice->paid_amount,
            'remaining_amount' => $invoice->remaining_amount,
            'status'           => $invoice->status,
            'customer'         => $invoice->customer,
            'notes'            => $invoice->notes,
            'sales_rep_name'   => $invoice->sales_rep_name,
            'created_by'       => $invoice->createdBy,
            'created_at'       => $invoice->created_at,
            'items'            => $items,
            'summary_by_category' => $summaryByCategory,
        ]);
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

    public function destroy(int $id): JsonResponse
    {
        $invoice = $this->invoiceService->delete(
            storeId: Auth::user()->getStoreId(),
            invoiceId: $id,
            deletedBy: Auth::id(),
        );

        return response()->json([
            'message' => 'تم حذف فاتورة البيع بنجاح.',
            'invoice' => $invoice,
        ]);
    }

    public function getItems(int $id): JsonResponse
    {
        $invoice = SalesInvoice::with('items.variant.product.category')
            ->where('store_id', Auth::user()->getStoreId())
            ->findOrFail($id);

        $items = $invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_amount' => $item->total_price,
            ];
        })->values();

        return response()->json([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'items' => $items,
        ]);
    }

    public function repsStats(): JsonResponse
    {
        $storeId = Auth::user()->getStoreId();

        $reps = SalesInvoice::where('store_id', $storeId)
            ->whereNotNull('sales_rep_name')
            ->where('sales_rep_name', '<>', '')
            ->select('sales_rep_name')
            ->distinct()
            ->pluck('sales_rep_name');

        $stats = [];
        foreach ($reps as $rep) {
            // Unique customers
            $customersCount = SalesInvoice::where('store_id', $storeId)
                ->where('sales_rep_name', $rep)
                ->distinct()
                ->count('customer_id');

            // Sales amount (net_amount of confirmed invoices)
            $salesAmount = (float) SalesInvoice::where('store_id', $storeId)
                ->where('sales_rep_name', $rep)
                ->confirmed()
                ->sum('net_amount');

            // Paid amount from invoices (collected at sale time)
            $invoiceCollected = (float) SalesInvoice::where('store_id', $storeId)
                ->where('sales_rep_name', $rep)
                ->confirmed()
                ->sum('paid_amount');

            // Paid amount from direct payments (credits) by customers who belong to this rep
            // A customer belongs to this rep if their latest sales invoice was dealt with by this rep.
            $customerIds = \Illuminate\Support\Facades\DB::table('sales_invoices as si1')
                ->select('si1.customer_id')
                ->where('si1.store_id', $storeId)
                ->where('si1.sales_rep_name', $rep)
                ->whereNotExists(function ($query) use ($storeId) {
                    $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('sales_invoices as si2')
                        ->whereColumn('si2.customer_id', 'si1.customer_id')
                        ->where('si2.store_id', $storeId)
                        ->where('si2.created_at', '>', 'si1.created_at');
                })
                ->pluck('customer_id')
                ->toArray();

            $receiptCollected = 0.0;
            if (!empty($customerIds)) {
                $receiptCollected = (float) \Illuminate\Support\Facades\DB::table('financial_transactions')
                    ->where('store_id', $storeId)
                    ->where('party_type', 'customer')
                    ->whereIn('party_id', $customerIds)
                    ->where('type', 'credit')
                    ->where('reference_type', 'direct_payment')
                    ->sum('amount');
            }

            $totalCollected = $invoiceCollected + $receiptCollected;

            $stats[] = [
                'sales_rep_name'   => $rep,
                'customers_count'  => $customersCount,
                'sales_amount'     => $salesAmount,
                'collected_amount' => $totalCollected,
                'invoice_collected'=> $invoiceCollected,
                'receipt_collected'=> $receiptCollected,
            ];
        }

        usort($stats, fn($a, $b) => $b['sales_amount'] <=> $a['sales_amount']);

        return response()->json($stats);
    }
}
