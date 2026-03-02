<?php

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
           'party_id' => ['required', 'integer'],
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'notes'    => ['nullable', 'string'],
            'date'     => ['nullable', 'date'],
        ];
    }
    public function messages(): array
    {
        return [
            'party_id.required' => 'يرجى تحديد العميل أو المورد.',
            'amount.required'   => 'يرجى إدخال مبلغ الدفع.',
            'amount.min'        => 'يجب أن يكون مبلغ الدفع 0.01 أو أكثر.',
        
        ];
    }
}
