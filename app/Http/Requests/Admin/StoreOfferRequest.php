<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
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
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'title' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'discount_value' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->input('discount_type') === 'percentage' && $value > 100) {
                        $fail('نسبة الخصم يجب ألا تتجاوز 100%.');
                    }
                },
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'in' => 'قيمة :attribute غير صحيحة.',
            'date' => 'حقل :attribute يجب أن يكون تاريخاً صحيحاً.',
            'after_or_equal' => 'حقل :attribute يجب أن يكون بعد أو يساوي تاريخ البداية.',
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
        ];
    }

    public function attributes(): array
    {
        return [
            'package_id' => 'الباقة',
            'title' => 'عنوان العرض',
            'image' => 'الصورة',
            'discount_type' => 'نوع الخصم',
            'discount_value' => 'قيمة الخصم',
            'starts_at' => 'تاريخ البداية',
            'ends_at' => 'تاريخ الانتهاء',
            'is_active' => 'الحالة (فعّال)',
        ];
    }
}
