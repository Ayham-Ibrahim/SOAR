<?php

namespace App\Http\Requests\Video;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteVideoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every part exactly once: as many entries as total_parts, each number
     * in 1..total_parts, none repeated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $totalParts = max((int) $this->route('video')->total_parts, 1);

        return [
            'parts' => ['required', 'array', 'size:'.$totalParts],
            'parts.*' => ['required', 'array:part_number,etag'],
            'parts.*.part_number' => ['required', 'integer', 'distinct', 'min:1', 'max:'.$totalParts],
            'parts.*.etag' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'array' => 'حقل :attribute غير صالح.',
            'parts.size' => 'يجب إرسال جميع الأجزاء (:size جزءاً).',
            'parts.*.part_number.integer' => 'رقم الجزء يجب أن يكون رقماً صحيحاً.',
            'parts.*.part_number.distinct' => 'أرقام الأجزاء يجب ألا تتكرر.',
            'parts.*.part_number.min' => 'رقم الجزء غير صالح.',
            'parts.*.part_number.max' => 'رقم الجزء غير صالح.',
            'parts.*.etag.string' => 'قيمة ETag غير صالحة.',
        ];
    }

    public function attributes(): array
    {
        return ['parts' => 'الأجزاء'];
    }

    /**
     * @return list<array{part_number: int, etag: string}>
     */
    public function parts(): array
    {
        return array_map(fn (array $part) => [
            'part_number' => (int) $part['part_number'],
            'etag' => (string) $part['etag'],
        ], $this->validated('parts'));
    }
}
