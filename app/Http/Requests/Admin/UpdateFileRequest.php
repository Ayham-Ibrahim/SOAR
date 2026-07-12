<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFileRequest extends FormRequest
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
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:20480'],
            'type' => ['sometimes', 'string', 'in:pdf,worksheet'],
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
            'mimes' => 'حقل :attribute يجب أن يكون من نوع: :values.',
            'in' => 'قيمة :attribute غير صحيحة.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lesson_id' => 'الدرس',
            'title' => 'عنوان الملف',
            'file' => 'الملف',
            'type' => 'نوع الملف',
        ];
    }
}
