<?php

namespace App\Http\Requests;

use App\Models\ParentAccountRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreParentAccountRequestRequest extends FormRequest
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
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parents', 'phone'),
                Rule::unique('parent_account_requests', 'parent_phone')->where('status', 'pending'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasPending = ParentAccountRequest::query()
                    ->where('requested_by_student_id', $this->user()->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPending) {
                    $validator->errors()->add('parent_phone', 'لديك طلب إنشاء حساب ولي أمر قيد المراجعة بالفعل.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'min' => 'حقل :attribute يجب أن يحتوي على :min رموز على الأقل.',
            'confirmed' => 'تأكيد :attribute غير مطابق.',
            'unique' => 'قيمة :attribute مستخدمة بالفعل.',
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_name' => 'اسم ولي الأمر',
            'parent_phone' => 'رقم هاتف ولي الأمر',
            'password' => 'كلمة المرور',
        ];
    }
}
