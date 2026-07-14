<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'unique:users,phone'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'fcm_token' => ['nullable', 'string'],
            // Informational only — never used to gate or filter content.
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
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
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'phone' => 'رقم الهاتف',
            'gender' => 'النوع',
            'age' => 'العمر',
            'avatar' => 'الصورة',
            'password' => 'كلمة المرور',
            'fcm_token' => 'رمز الجهاز',
            'governorate_id' => 'المحافظة',
            'school_id' => 'المدرسة',
        ];
    }
}
