<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'subscription_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'array' => 'حقل :attribute يجب أن يكون قائمة.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'lt' => 'حقل :attribute يجب أن يكون أقل من السعر الأساسي.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان الباقة',
            'description' => 'الوصف',
            'image' => 'الصورة',
            'price' => 'السعر',
            'discount_price' => 'السعر بعد الخصم',
            'subscription_days' => 'مدة الاشتراك (أيام)',
            'is_active' => 'الحالة (فعّالة)',
            'course_ids' => 'الدورات المرتبطة',
        ];
    }
}
