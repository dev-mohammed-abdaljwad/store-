<?php

namespace App\Http\Requests\Api\V1\Cash;

use Illuminate\Foundation\Http\FormRequest;

class OpeningBalanceRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
    public function messages(): array
    {
        return [
            'amount.required' => 'يرجى إدخال مبلغ الرصيد الافتتاحي.',
            'amount.numeric'  => 'يجب أن يكون مبلغ الرصيد الافتتاحي رقمًا.',
            'amount.min'      => 'يجب أن يكون مبلغ الرصيد الافتتاحي صفرًا أو أكثر.',
        ];
    }
}
