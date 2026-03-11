<?php

namespace App\Http\Requests\Api\V1\PurchaseReturn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->user()?->store_id;

        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')
                    ->where(fn($query) => $query
                        ->where('store_id', $storeId)
                        ->whereNull('deleted_at')),
            ],
            'purchase_invoice_id' => [
                'nullable',
                Rule::exists('purchase_invoices', 'id')
                    ->where(fn($query) => $query
                        ->where('store_id', $storeId)
                        ->where('supplier_id', $this->input('supplier_id'))
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
