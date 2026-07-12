<?php

namespace App\Http\Requests\Admin;

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
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
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
