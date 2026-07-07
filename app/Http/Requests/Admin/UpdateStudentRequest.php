<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
        $studentId = $this->route('student')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', Rule::unique('users', 'phone')->ignore($studentId)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($studentId)],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:4096'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
