<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLessonRequest extends FormRequest
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
            'course_ids' => ['sometimes', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lesson = $this->route('lesson');

                $courseIds = $this->input('course_ids');
                $courseId = $this->input('course_id');
                $unitId = $this->input('unit_id', $lesson?->unit_id);

                if (! $unitId) {
                    return;
                }

                if ($courseIds === null) {
                    $courseIds = $courseId ? [$courseId] : ($lesson?->courses()->pluck('courses.id')->all() ?? []);
                }

                if (! $courseIds) {
                    return;
                }

                $unit = Unit::find($unitId);
                $courses = Course::whereIn('id', $courseIds)->get();

                if (! $unit || $courses->count() !== count($courseIds)) {
                    return;
                }

                $subjectIds = $courses->pluck('subject_id')->unique();

                if ($subjectIds->count() > 1 || $subjectIds->first() !== $unit->subject_id) {
                    $validator->errors()->add(
                        'unit_id',
                        'الوحدة المحددة يجب أن تنتمي لنفس مادة جميع الدورات المحددة.'
                    );
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
            'boolean' => 'حقل :attribute يجب أن يكون صحيح أو خاطئ.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
        ];
    }

    public function attributes(): array
    {
        return [
            'course_ids' => 'قائمة الدورات',
            'course_id' => 'الدورة',
            'unit_id' => 'الوحدة',
            'title' => 'عنوان الدرس',
            'order' => 'الترتيب',
            'is_free' => 'مجاني',
        ];
    }
}
