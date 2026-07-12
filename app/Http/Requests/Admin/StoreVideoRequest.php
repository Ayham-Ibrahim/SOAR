<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRequest extends FormRequest
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
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'video' => ['required', 'file', 'mimes:mp4,webm,ogg,mov,wmv', 'max:512000'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['nullable', 'boolean'],
            'is_downloadable' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'file' => 'حقل :attribute يجب أن يكون ملفاً.',
            'mimes' => 'حقل :attribute يجب أن يكون ملف فيديو من نوع: :values.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lesson_id' => 'الدرس',
            'title' => 'عنوان الفيديو',
            'video' => 'ملف الفيديو',
            'thumbnail' => 'الصورة المصغّرة',
            'duration_seconds' => 'المدة (بالثواني)',
            'order' => 'الترتيب',
            'is_free' => 'مجاني',
            'is_downloadable' => 'قابل للتحميل',
        ];
    }
}
