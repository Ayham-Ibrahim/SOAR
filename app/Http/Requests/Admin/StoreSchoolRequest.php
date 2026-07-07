<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
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
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
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
            'governorate_id' => 'المحافظة',
            'name' => 'اسم المدرسة',
            'image' => 'الصورة',
        ];
    }
}
