<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Bank/transfer details shown before the student uploads a receipt.
     * Stored as a JSON string in the "payment_info" setting; decoded here
     * so the app gets a proper object.
     */
    public function paymentInfo()
    {
        $value = Setting::get('payment_info');

        return $this->success($value ? json_decode($value, true) : null, 'تم جلب معلومات الدفع بنجاح');
    }
}
