<?php

namespace App\Http\Requests\Api\V1\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesInvoiceRequest extends FormRequest
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
            'customer_id'                    => ['required', 'exists:customers,id'],
            'paid_amount'                    => ['required', 'numeric', 'min:0'],
            'notes'                          => ['nullable', 'string'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.variant_id'             => ['required', 'exists:product_variants,id'],
            'items.*.quantity'       => ['required', 'numeric', 'min:1'],
            'items.*.unit_price'             => ['required', 'numeric', 'min:0'],
        ];
    }
}
