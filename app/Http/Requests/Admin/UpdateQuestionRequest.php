<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
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
            'exam_id' => ['sometimes', 'integer', 'exists:exams,id'],
            'text' => ['sometimes', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
        ];
    }

    public function attributes(): array
    {
        return [
            'exam_id' => 'الامتحان',
            'text' => 'نص السؤال',
            'order' => 'الترتيب',
        ];
    }
}
