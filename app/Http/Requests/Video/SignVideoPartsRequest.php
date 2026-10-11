<?php

namespace App\Http\Requests\Video;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignVideoPartsRequest extends FormRequest
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
        $totalParts = (int) $this->route('video')->total_parts;

        return [
            'part_numbers' => ['required', 'array', 'min:1', 'max:20'],
            'part_numbers.*' => ['integer', 'distinct', 'min:1', 'max:'.max($totalParts, 1)],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'array' => 'حقل :attribute يجب أن يكون مصفوفة.',
            'part_numbers.min' => 'يجب طلب جزء واحد على الأقل.',
            'part_numbers.max' => 'لا يمكن طلب أكثر من :max جزءاً في المرة الواحدة.',
            'part_numbers.*.integer' => 'رقم الجزء يجب أن يكون رقماً صحيحاً.',
            'part_numbers.*.distinct' => 'أرقام الأجزاء يجب ألا تتكرر.',
            'part_numbers.*.min' => 'رقم الجزء غير صالح.',
            'part_numbers.*.max' => 'رقم الجزء غير صالح.',
        ];
    }

    public function attributes(): array
    {
        return ['part_numbers' => 'أرقام الأجزاء'];
    }
}
