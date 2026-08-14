<?php

namespace App\Http\Requests\Admin;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;

class StoreBroadcastNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add admin authorization logic if needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validTargetTypes = array_keys(Notification::getTargetTypes());

        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:1000'],
            'target_types' => ['required', 'array', 'min:1'],
            'target_types.*' => ['string', 'in:' . implode(',', $validTargetTypes)],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:parents,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الإشعار مطلوب',
            'title.max' => 'عنوان الإشعار يجب ألا يتجاوز 255 حرف',
            'content.required' => 'محتوى الإشعار مطلوب',
            'content.max' => 'محتوى الإشعار يجب ألا يتجاوز 1000 حرف',
            'target_types.required' => 'يجب تحديد جهة الإرسال: الطلاب، أولياء الأمور، أو الكل.',
            'target_types.min' => 'يجب تحديد جهة إرسال واحدة على الأقل.',
            'target_types.*.in' => 'نوع المستهدف غير صالح',
            'governorate_id.exists' => 'المحافظة غير موجودة',
            'gender.in' => 'الجنس يجب أن يكون male أو female',
            'student_ids.array' => 'قائمة الطلاب يجب أن تكون مصفوفة',
            'student_ids.*.exists' => 'أحد الطلاب غير موجود',
            'parent_ids.array' => 'قائمة أولياء الأمور يجب أن تكون مصفوفة',
            'parent_ids.*.exists' => 'أحد أولياء الأمور غير موجود',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $targetTypes = $this->input('target_types', []);
                $hasSelectedIds = ! empty($this->input('student_ids')) || ! empty($this->input('parent_ids'));

                if (empty($targetTypes) && ! $hasSelectedIds) {
                    $validator->errors()->add('target_types', 'يجب تحديد جهة الإرسال: الطلاب، أولياء الأمور، أو الكل.');
                }
            },
        ];
    }
}
