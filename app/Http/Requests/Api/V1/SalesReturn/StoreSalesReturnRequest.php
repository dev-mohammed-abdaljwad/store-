<?php

namespace App\Http\Requests\Api\V1\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->user()?->store_id;

        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->where(fn($query) => $query
                        ->where('store_id', $storeId)
                        ->whereNull('deleted_at')),
            ],
            'sales_invoice_id' => [
                'nullable',
                Rule::exists('sales_invoices', 'id')
                    ->where(fn($query) => $query
                        ->where('store_id', $storeId)
                        ->where('customer_id', $this->input('customer_id'))
                        ->whereNull('deleted_at')),
            ],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')
                    ->where(fn($query) => $query
                        ->where('store_id', $storeId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
