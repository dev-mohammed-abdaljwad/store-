<?php

namespace App\Http\Requests\Api\V1\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        $storeId = $this->user()?->getStoreId();
        $categoryParam = $this->route('category');
        $categoryId = is_object($categoryParam) ? $categoryParam->id : $categoryParam;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query
                        ->where('store_id', $storeId)
                        ->whereNull('deleted_at'))
                    ->ignore($categoryId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التصنيف مطلوب.',
            'name.unique' => 'هذا التصنيف موجود بالفعل في متجرك.',
            'name.max' => 'اسم التصنيف طويل جداً.',
        ];
    }
}
