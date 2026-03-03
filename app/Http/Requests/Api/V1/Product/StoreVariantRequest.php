<?php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الحجم مطلوب.',
            'name.max' => 'اسم الحجم يجب ألا يتجاوز 100 حرف.',
            'purchase_price.required' => 'سعر الشراء مطلوب.',
            'purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقمًا.',
            'sale_price.required' => 'سعر البيع مطلوب.',
            'sale_price.numeric' => 'سعر البيع يجب أن يكون رقمًا.',
        ];
    }
}
