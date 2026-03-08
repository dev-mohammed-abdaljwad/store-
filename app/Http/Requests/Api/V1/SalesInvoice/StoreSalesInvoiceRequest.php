<?php

namespace App\Http\Requests\Api\V1\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $storeId = $this->user()?->store_id;

        return [
            'invoice_number'                 => [
                'required',
                'string',
                'max:100',
                Rule::unique('sales_invoices', 'invoice_number')
                    ->where(fn($query) => $query->where('store_id', $storeId)),
            ],
            'customer_id'                    => ['required', 'exists:customers,id'],
            'paid_amount'                    => ['required', 'numeric', 'min:0'],
            'discount_amount'                => ['nullable', 'numeric', 'min:0'],
            'notes'                          => ['nullable', 'string'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.variant_id'             => ['required', 'exists:product_variants,id'],
            'items.*.quantity'               => ['required', 'numeric', 'min:1'],
            'items.*.unit_price'             => ['required', 'numeric', 'min:0'],
        ];
    }
        public function messages(): array
        {
            return [
                'invoice_number.required'      => 'يرجى إدخال رقم الفاتورة.',
                'invoice_number.string'        => 'رقم الفاتورة يجب أن يكون نصًا.',
                'invoice_number.max'           => 'رقم الفاتورة يجب ألا يتجاوز 100 حرف.',
                'invoice_number.unique'        => 'رقم الفاتورة مستخدم بالفعل داخل المتجر.',
                'customer_id.required'           => 'يرجى تحديد العميل.',
                'customer_id.exists'             => 'العميل المحدد غير موجود.',
                'paid_amount.required'           => 'يرجى إدخال المبلغ المدفوع.',
                'paid_amount.numeric'            => 'يجب أن يكون المبلغ المدفوع رقمًا.',
                'paid_amount.min'                => 'يجب أن يكون المبلغ المدفوع صفرًا أو أكثر.',
                'discount_amount.numeric'        => 'يجب أن يكون الخصم رقمًا.',
                'discount_amount.min'            => 'يجب أن يكون الخصم صفرًا أو أكثر.',
                'items.required'                 => 'يرجى إضافة عنصر واحد على الأقل إلى الفاتورة.',
                'items.array'                    => 'يجب أن تكون العناصر في شكل مصفوفة.',
                'items.min'                      => 'يجب إضافة عنصر واحد على الأقل إلى الفاتورة.',
                'items.*.variant_id.required'    => 'يرجى تحديد الحجم لكل عنصر.',
                'items.*.variant_id.exists'      => 'أحد الأحجام المحددة غير موجود.',
                'items.*.quantity.required'  => 'يرجى إدخال الكمية لكل عنصر.',
                'items.*.quantity.numeric'   => 'يجب أن تكون الكمية رقمًا.',
                'items.*.quantity.min'       => 'يجب أن تكون الكمية 1 أو أكثر.',
                'items.*.unit_price.required'        => 'يرجى إدخال سعر الوحدة لكل عنصر.',
                'items.*.unit_price.numeric'         => 'يجب أن يكون سعر الوحدة رقمًا.',
                'items.*.unit_price.min'             => 'يجب أن يكون سعر الوحدة صفرًا أو أكثر.',
            ];
        }
    
}
