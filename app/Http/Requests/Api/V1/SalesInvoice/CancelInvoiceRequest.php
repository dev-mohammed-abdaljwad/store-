<?php

namespace App\Http\Requests\Api\V1\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];

    }
    public function messages(): array
    {
        return [
            'reason.required' => 'يرجى تقديم سبب لإلغاء الفاتورة.',
            'reason.string'   => 'يجب أن يكون السبب نصًا.',
            'reason.max'      => 'يجب ألا يتجاوز السبب 500 حرف.',
        ];
    }
}
