<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'subscription_days' => ['required', 'integer', 'min:1'],
            'free_videos_count' => ['nullable', 'integer', 'min:0'],
            'allow_download' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
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
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'lt' => 'حقل :attribute يجب أن يكون أقل من السعر الأساسي.',
        ];
    }

    public function attributes(): array
    {
        return [
            'subject_id' => 'المادة',
            'teacher_id' => 'المدرّس',
            'title' => 'عنوان الدورة',
            'description' => 'وصف الدورة',
            'price' => 'السعر',
            'discount_price' => 'السعر بعد الخصم',
            'subscription_days' => 'مدة الاشتراك (أيام)',
            'free_videos_count' => 'عدد الفيديوهات المجانية',
            'allow_download' => 'السماح بالتحميل',
            'is_active' => 'الحالة (فعّالة)',
        ];
    }
}
