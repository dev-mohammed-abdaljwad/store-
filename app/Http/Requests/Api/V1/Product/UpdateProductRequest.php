<?php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return  true;
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
            'sku' => 'nullable|string',
            'unit' => 'nullable|string',
            'purchase_price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'low_stock_threshold' => 'nullable|numeric',
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
            'unit.string' => 'الوحدة يجب أن تكون نص',
            'purchase_price.numeric' => 'سعر الشراء يجب أن يكون رقم',
            'sale_price.numeric' => 'سعر البيع يجب أن يكون رقم',
            'low_stock_threshold.numeric' => 'الحد الأدنى للكمية يجب أن يكون رقم',
        ];
    }
}
