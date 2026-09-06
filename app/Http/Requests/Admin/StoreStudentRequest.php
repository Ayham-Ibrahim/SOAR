<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^\S+\s+\S+\s+\S+(?:\s+.*)?$/u'],
            'phone' => ['required', 'string', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Informational only — never used to gate or filter content.
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sub_category_id' => ['required', 'integer', 'exists:sub_categories,id'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'study_type_id' => ['required', 'integer', 'exists:study_types,id'],
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
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'regex' => 'حقل :attribute يجب أن يتضمن الاسم الثلاثي.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم الثلاثي',
            'phone' => 'رقم الهاتف',
            'email' => 'البريد الإلكتروني',
            'gender' => 'النوع',
            'age' => 'العمر',
            'avatar' => 'الصورة',
            'password' => 'كلمة المرور',
            'governorate_id' => 'المحافظة',
            'category_id' => 'المرحلة الدراسية',
            'sub_category_id' => 'الصف',
            'school_id' => 'المدرسة',
            'study_type_id' => 'نوع الدراسة',
        ];
    }
}
