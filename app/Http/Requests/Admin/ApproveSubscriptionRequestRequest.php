<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveSubscriptionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
    * access_ends_at optionally applies to a direct single-course request.
    * When omitted, the subscription gets one full year. An offer request's expiry is always
     * derived from the offer's access_duration_days at approval time.
     *
    * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $isDirect = (bool) $this->route('subscription_request')?->course_id;

        return [
            'access_ends_at' => [$isDirect ? 'nullable' : 'prohibited', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $request = $this->route('subscription_request');

                if ($request && $request->status !== 'pending') {
                    $validator->errors()->add('status', 'تمت مراجعة هذا الطلب مسبقاً.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'prohibited' => 'حقل :attribute غير مسموح به لطلبات العروض — تُحسب المدة تلقائياً من مدة العرض.',
            'date' => 'حقل :attribute يجب أن يكون تاريخاً صحيحاً.',
            'after' => 'حقل :attribute يجب أن يكون تاريخاً بعد اليوم.',
        ];
    }

    public function attributes(): array
    {
        return [
            'access_ends_at' => 'تاريخ انتهاء الوصول',
        ];
    }
}
