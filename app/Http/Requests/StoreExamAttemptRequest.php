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
        $timeRules = ['nullable', 'integer', 'min:0'];

        return [
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'time_spent_seconds' => $timeRules,
            'answers' => [$isWritten ? 'prohibited' : 'required', 'array'],
            'answers.*.question_id' => ['required_with:answers', 'integer', 'exists:questions,id'],
            'answers.*.choice_id' => ['required_with:answers', 'integer', 'exists:choices,id'],
            'submission_files' => [
                $isWritten ? 'required' : 'prohibited',
                'array',
                'min:1',
                'max:10',
            ],
            'submission_files.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png',
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
            'submission_files' => 'صور الحل',
            'submission_files.*' => 'صورة الحل',
        ];
    }
}
