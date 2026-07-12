<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvertisementRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'link' => ['nullable', 'string', 'url', 'max:2048'],
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
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'url' => 'حقل :attribute يجب أن يكون رابطاً صحيحاً.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'image' => 'الصورة',
            'link' => 'رابط الوجهة',
            'order' => 'الترتيب',
            'is_active' => 'الحالة (فعّال)',
        ];
    }
}
