<?php

namespace App\Http\Requests\Api\V1\PurchaseInvoice;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
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
            'supplier_id'                    => ['required', 'exists:suppliers,id'],
            'paid_amount'                    => ['required', 'numeric', 'min:0'],
            'notes'                          => ['nullable', 'string'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.product_id'             => ['required', 'exists:products,id'],
            'items.*.ordered_quantity'       => ['required', 'numeric', 'min:0.001'],
            'items.*.received_quantity'      => ['required', 'numeric', 'min:0'],
            'items.*.unit_price'             => ['required', 'numeric', 'min:0'],
        ];
    }
    public function messages(): array
    {
        return [
            'supplier_id.required'           => 'يرجى تحديد المورد.',
            'supplier_id.exists'             => 'المورد المحدد غير موجود.',
            'paid_amount.required'           => 'يرجى إدخال المبلغ المدفوع.',
            'paid_amount.numeric'            => 'يجب أن يكون المبلغ المدفوع رقمًا.',
            'paid_amount.min'                => 'يجب أن يكون المبلغ المدفوع صفرًا أو أكثر.',
            'items.required'                 => 'يرجى إضافة عنصر واحد على الأقل إلى الفاتورة.',
            'items.array'                    => 'يجب أن تكون العناصر في شكل مصفوفة.',
            'items.min'                      => 'يجب إضافة عنصر واحد على الأقل إلى الفاتورة.',
            'items.*.product_id.required'    => 'يرجى تحديد المنتج لكل عنصر.',
            'items.*.product_id.exists'      => 'أحد المنتجات المحددة غير موجود.',
            'items.*.ordered_quantity.required'  => 'يرجى إدخال الكمية المطلوبة لكل عنصر.',
            'items.*.ordered_quantity.numeric'   => 'يجب أن تكون الكمية المطلوبة رقمًا.',
            'items.*.ordered_quantity.min'       => 'يجب أن تكون الكمية المطلوبة 0.001 أو أكثر.',
            'items.*.received_quantity.required' => 'يرجى إدخال الكمية المستلمة لكل عنصر.',
            'items.*.received_quantity.numeric'  => 'يجب أن تكون الكمية المستلمة رقمًا.',
            'items.*.received_quantity.min'      => 'يجب أن تكون الكمية المستلمة صفرًا أو أكثر.',
            'items.*.unit_price.required'        => 'يرجى إدخال سعر الوحدة لكل عنصر.',
            'items.*.unit_price.numeric'         => 'يجب أن يكون سعر الوحدة رقمًا.',
            'items.*.unit_price.min'             => 'يجب أن يكون سعر الوحدة صفرًا أو أكثر.',
        ];
    }
}
