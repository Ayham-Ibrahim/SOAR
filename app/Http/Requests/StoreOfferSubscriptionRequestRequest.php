<?php

namespace App\Http\Requests;

use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfferSubscriptionRequestRequest extends FormRequest
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
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
            'receipt_image' => ['required', 'image', 'max:4096'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            // Temporarily optional so app versions from before payment methods can
            // still subscribe — make it 'required' once those are phased out.
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $offer = Offer::find($this->input('offer_id'));

                if ($offer && ! $offer->isPurchasableNow()) {
                    $validator->errors()->add('offer_id', 'العرض غير متاح.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_id.exists' => 'طريقة الدفع المختارة غير متاحة.',
            'required' => 'حقل :attribute مطلوب.',
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'exists' => 'القيمة المحددة لحقل :attribute غير موجودة.',
            'image' => 'حقل :attribute يجب أن يكون صورة.',
            'numeric' => 'حقل :attribute يجب أن يكون رقمًا.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute أكبر من الحد المسموح به.',
        ];
    }

    public function attributes(): array
    {
        return [
            'offer_id' => 'العرض',
            'receipt_image' => 'صورة إيصال التحويل',
            'amount' => 'المبلغ',
            'payment_method_id' => 'طريقة الدفع',
        ];
    }
}
