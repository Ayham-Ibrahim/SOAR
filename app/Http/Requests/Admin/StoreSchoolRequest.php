<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolRequest extends FormRequest
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
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
