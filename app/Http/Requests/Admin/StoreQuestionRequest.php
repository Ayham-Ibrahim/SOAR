<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
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
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'text' => ['required', 'string'],
            'points' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'integer', 'min:0'],
            'choices' => ['nullable', 'array', 'min:1'],
            'choices.*.text' => ['required', 'string'],
            'choices.*.is_correct' => ['nullable', 'boolean'],
            'choices.*.order' => ['nullable', 'integer', 'min:0'],
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
            'points' => 'علامة السؤال',
            'order' => 'الترتيب',
            'choices' => 'الخيارات',
            'choices.*.text' => 'نص الخيار',
            'choices.*.is_correct' => 'علامة الخيار الصحيح',
            'choices.*.order' => 'ترتيب الخيار',
        ];
    }
}
