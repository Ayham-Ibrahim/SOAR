<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
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
            'subject_id' => 'المادة',
            'title' => 'عنوان الوحدة',
            'order' => 'الترتيب',
        ];
    }
}
