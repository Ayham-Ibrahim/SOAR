<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'offer_starts_at' => ['sometimes', 'date'],
            'offer_ends_at' => ['sometimes', 'date', 'after:offer_starts_at'],
            'access_duration_days' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'course_ids' => ['sometimes', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'date' => 'حقل :attribute يجب أن يكون تاريخاً صحيحاً.',
            'after' => 'حقل :attribute يجب أن يكون بعد :date.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'array' => 'حقل :attribute يجب أن يكون قائمة.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان العرض',
            'description' => 'الوصف',
            'image' => 'الصورة',
            'price' => 'السعر',
            'offer_starts_at' => 'بداية فترة الشراء',
            'offer_ends_at' => 'نهاية فترة الشراء',
            'access_duration_days' => 'مدة فتح الدورات (أيام)',
            'is_active' => 'الحالة (فعّال)',
            'course_ids' => 'قائمة الدورات',
            'course_ids.*' => 'الدورة',
        ];
    }
}
