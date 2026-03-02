<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم العميل.',
            'name.string' => 'يجب أن يكون اسم العميل نصًا.',
            'name.max' => 'يجب أن يكون اسم العميل أقل من 255 حرفًا.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'phone.string' => 'يجب أن يكون رقم الهاتف نصًا.',
            'phone.max' => 'يجب أن يكون رقم الهاتف أقل من 20 حرفًا.',
            'address.string' => 'يجب أن يكون العنوان نصًا.',
            'address.max' => 'يجب أن يكون العنوان أقل من 255 حرفًا.',
        ];
    }
}
