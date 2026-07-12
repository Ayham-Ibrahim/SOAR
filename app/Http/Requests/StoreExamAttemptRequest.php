<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamAttemptRequest extends FormRequest
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
        $exam = Exam::find($this->input('exam_id'));
        $isWritten = $exam?->type === 'written';

        return [
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'answers' => [$isWritten ? 'prohibited' : 'required', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'exists:questions,id'],
            'answers.*.choice_id' => ['required_with:answers', 'integer', 'exists:choices,id'],
            'submission_file' => [
                $isWritten ? 'required' : 'prohibited',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:20480',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'required_with' => 'حقل :attribute مطلوب.',
            'prohibited' => 'حقل :attribute غير مسموح به لهذا النوع من الامتحان.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'array' => 'حقل :attribute يجب أن يكون قائمة.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'file' => 'حقل :attribute يجب أن يكون ملفاً.',
            'mimes' => 'حقل :attribute يجب أن يكون من نوع: :values.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
        ];
    }

    public function attributes(): array
    {
        return [
            'exam_id' => 'الامتحان',
            'answers' => 'الإجابات',
            'answers.*.question_id' => 'السؤال',
            'answers.*.choice_id' => 'الخيار المختار',
            'submission_file' => 'ملف الحل',
        ];
    }
}
