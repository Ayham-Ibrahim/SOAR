<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
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
            'min' => 'حقل :attribute يجب أن يحتوي على :min رموز على الأقل.',
            'confirmed' => 'تأكيد :attribute غير مطابق.',
            'in' => 'قيمة :attribute غير صحيحة.',
            'email' => 'حقل :attribute يجب أن يكون بريدًا إلكترونيًا صالحًا.',
            'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
            'unique' => 'قيمة :attribute مستخدمة بالفعل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'image' => 'الصورة',
            'order' => 'الترتيب',
            'is_active' => 'نشط',
        ];
    }
}
