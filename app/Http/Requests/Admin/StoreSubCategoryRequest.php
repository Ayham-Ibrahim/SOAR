<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_categories', 'name')->where('category_id', $this->input('category_id')),
            ],
            'image' => ['nullable', 'image', 'max:4096'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'unique' => 'قيمة :attribute مستخدمة بالفعل ضمن نفس القسم.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'المرحلة',
            'name' => 'اسم الفرع',
            'image' => 'الصورة',
            'order' => 'الترتيب',
            'is_active' => 'نشط',
        ];
    }
}
