<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
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
            'option_name' => ['nullable', 'string', 'max:255'],
            'person_name' => ['nullable', 'string', 'max:255'],
            'person_phone' => ['nullable', 'string', 'max:50'],
            'qr_code' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'mimes' => 'حقل :attribute يجب أن يكون من نوع JPG أو JPEG أو PNG.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيحاً أو خاطئاً.',
        ];
    }

    public function attributes(): array
    {
        return [
            'option_name' => 'اسم خيار الدفع',
            'person_name' => 'اسم الشخص',
            'person_phone' => 'رقم الشخص',
            'qr_code' => 'صورة QR Code',
            'location' => 'الموقع',
            'is_active' => 'الحالة',
        ];
    }
}
