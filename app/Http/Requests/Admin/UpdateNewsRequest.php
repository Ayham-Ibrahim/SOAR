<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNewsRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'body' => 'المحتوى',
            'image' => 'الصورة',
            'is_active' => 'الحالة (فعّال)',
        ];
    }
}
