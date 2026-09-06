<?php

namespace App\Http\Requests\Admin;

use App\Models\ExamAttempt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GradeExamAttemptRequest extends FormRequest
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
            'score' => [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $attempt = $this->route('exam_attempt');
                    $totalScore = $attempt instanceof ExamAttempt ? $attempt->exam?->total_score : null;

                    if ($totalScore !== null && (float) $value > $totalScore) {
                        $fail("حقل {$attribute} يجب ألا يزيد عن العلامة النهائية للامتحان.");
                    }
                },
            ],
            'feedback' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute يجب ألا يزيد عن :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'score' => 'العلامة',
            'feedback' => 'ملاحظات المصحّح',
        ];
    }
}
