<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
            'value' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
        ];
    }

    public function attributes(): array
    {
        return [
            'value' => 'القيمة',
        ];
    }
}
