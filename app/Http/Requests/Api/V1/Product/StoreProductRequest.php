<?php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            'purchase_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|numeric|min:0',

        ];
    }
    public function message()
    {
        return [
            'category_id.required' => 'التصنيف مطلوب',
            'category_id.exists' => 'التصنيف غير موجود',
            'name.required' => 'الاسم مطلوب',
            'name.string' => 'الاسم يجب أن يكون نص',
            'name.max' => 'الاسم يجب أن يكون 255 حرف',
            'sku.string' => 'الرمز يجب أن يكون نص',
            'sku.max' => 'الرمز يجب أن يكون 255 حرف',
            'unit.string' => 'الوحدة يجب أن تكون نص',
            'unit.max' => 'الوحدة يجب أن تكون 255 حرف',
            'purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقم',
            'purchase_price.min' => 'سعر الشراء يجب أن يكون صفر أو أكثر',
            'sale_price.numeric' => 'سعر البيع يجب أن يكون رقم',
            'sale_price.min' => 'سعر البيع يجب أن يكون صفر أو أكثر',
            'low_stock_threshold.numeric' => 'الحد الأدنى للكمية يجب أن يكون رقم',
            'low_stock_threshold.min' => 'الحد الأدنى للكمية يجب أن يكون صفر أو أكثر',
        ];
    }
}
