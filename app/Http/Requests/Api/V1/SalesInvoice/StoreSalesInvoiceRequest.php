<?php

namespace App\Http\Requests\Api\V1\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoiceRequest extends FormRequest
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
            'customer_id'                    => ['required', 'exists:customers,id'],
            'paid_amount'                    => ['required', 'numeric', 'min:0'],
            'notes'                          => ['nullable', 'string'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.product_id'             => ['required', 'exists:products,id'],
            'items.*.quantity'       => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'             => ['required', 'numeric', 'min:0'],
        ];
    }
        public function messages(): array
        {
            return [
                'customer_id.required'           => 'يرجى تحديد العميل.',
                'customer_id.exists'             => 'العميل المحدد غير موجود.',
                'paid_amount.required'           => 'يرجى إدخال المبلغ المدفوع.',
                'paid_amount.numeric'            => 'يجب أن يكون المبلغ المدفوع رقمًا.',
                'paid_amount.min'                => 'يجب أن يكون المبلغ المدفوع صفرًا أو أكثر.',
                'items.required'                 => 'يرجى إضافة عنصر واحد على الأقل إلى الفاتورة.',
                'items.array'                    => 'يجب أن تكون العناصر في شكل مصفوفة.',
                'items.min'                      => 'يجب إضافة عنصر واحد على الأقل إلى الفاتورة.',
                'items.*.product_id.required'    => 'يرجى تحديد المنتج لكل عنصر.',
                'items.*.product_id.exists'      => 'أحد المنتجات المحددة غير موجود.',
                'items.*.quantity.required'  => 'يرجى إدخال الكمية لكل عنصر.',
                'items.*.quantity.numeric'   => 'يجب أن تكون الكمية رقمًا.',
                'items.*.quantity.min'       => 'يجب أن تكون الكمية 0.001 أو أكثر.',
                'items.*.unit_price.required'        => 'يرجى إدخال سعر الوحدة لكل عنصر.',
                'items.*.unit_price.numeric'         => 'يجب أن يكون سعر الوحدة رقمًا.',
                'items.*.unit_price.min'             => 'يجب أن يكون سعر الوحدة صفرًا أو أكثر.',
            ];
        }
    
}
