<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $branchId = $this->input('branch_id', $category?->branch_id);

        return [
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('branch_id', $branchId)->ignore($category?->id),
            ],
            'image' => ['nullable', 'image', 'max:4096'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
