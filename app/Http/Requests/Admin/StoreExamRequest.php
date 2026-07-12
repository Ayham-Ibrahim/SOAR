<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
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
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:mcq,written'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'in' => 'قيمة :attribute غير صحيحة.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute يجب ألا يزيد عن :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'course_id' => 'الدورة',
            'title' => 'عنوان الامتحان',
            'type' => 'نوع الامتحان',
            'description' => 'الوصف',
            'duration_minutes' => 'المدة (بالدقائق)',
            'passing_score' => 'علامة النجاح (%)',
            'is_active' => 'الحالة (فعّال)',
        ];
    }
}
