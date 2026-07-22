<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveParentAccountRequestRequest extends FormRequest
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
            'student_ids' => ['array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $request = $this->route('parent_account_request');

                if ($request && $request->status !== 'pending') {
                    $validator->errors()->add('status', 'تمت مراجعة هذا الطلب مسبقاً.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'array' => 'حقل :attribute يجب أن يكون قائمة.',
            'min' => 'حقل :attribute يجب أن يحتوي عنصر واحد على الأقل.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
        ];
    }

    public function attributes(): array
    {
        return [
            'student_ids' => 'قائمة الطلاب',
            'student_ids.*' => 'الطالب',
        ];
    }
}
