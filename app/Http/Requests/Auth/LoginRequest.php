<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'type' => ['required', 'string', 'in:user,parent'],
            'fcm_token' => ['nullable', 'string'], // Keeping this line for context
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
            'phone' => 'رقم الهاتف',
            'password' => 'كلمة المرور',
            'type' => 'النوع',
            'fcm_token' => 'رمز الجهاز',
        ];
    }
}
