<?php

namespace App\Http\Requests\Video;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StartVideoUploadRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('video.max_size')],
            'content_type' => ['required', 'string', Rule::in(config('video.content_types'))],
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** S3 multipart uploads stop at 10,000 parts. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $size = (int) $this->input('size');
                if ($size > 0 && ceil($size / (int) config('video.part_size')) > 10000) {
                    $validator->errors()->add('size', 'حجم الملف كبير جداً بالنسبة لحجم الأجزاء المحدد.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'size.max' => 'حجم الفيديو يجب ألا يتجاوز 2 غيغابايت.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
            'content_type.in' => 'نوع الفيديو غير مدعوم. الأنواع المسموحة: MP4, MOV, WebM.',
        ];
    }

    public function attributes(): array
    {
        return [
            'filename' => 'اسم الملف',
            'size' => 'حجم الملف',
            'content_type' => 'نوع الملف',
            'lesson_id' => 'الدرس',
            'title' => 'عنوان الفيديو',
        ];
    }
}
