<?php

namespace App\Http\Requests\Api\V1\Store;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     *  Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم المتجر.',
            'name.string' => 'يجب أن يكون اسم المتجر نصًا.',
            'name.max' => 'يجب أن يكون اسم المتجر أقل من 255 حرفًا.',
            'owner_name.required' => 'يرجى إدخال اسم المالك.',
            'owner_name.string' => 'يجب أن يكون اسم المالك نصًا.',
            'owner_name.max' => 'يجب أن يكون اسم المالك أقل من 255 حرفًا.',
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'يجب أن يكون البريد الإلكتروني صحيحًا.',
            'email.unique' => 'البريد الإلكتروني موجود بالفعل.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.string' => 'يجب أن تكون كلمة المرور نصًا.',
            'password.min' => 'يجب أن تكون كلمة المرور 6 أحرف على الأقل.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'phone.string' => 'يجب أن يكون رقم الهاتف نصًا.',
            'phone.max' => 'يجب أن يكون رقم الهاتف أقل من 20 حرفًا.',
            'address.required' => 'يرجى إدخال العنوان.',
            'address.string' => 'يجب أن يكون العنوان نصًا.',
            'address.max' => 'يجب أن يكون العنوان أقل من 255 حرفًا.',
        ];
    }
}
