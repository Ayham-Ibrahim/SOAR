<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\Admin\PaymentMethodService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $paymentMethodService)
    {
    }

    public function index(Request $request)
    {
        $paymentMethods = $request->user()?->tokenCan('dashboard')
            ? $this->paymentMethodService->list($request->integer('per_page', 15))
            : $this->paymentMethodService->activeList($request->integer('per_page', 15));

        return $this->paginate(
            $paymentMethods,
            'تم جلب طرق الدفع بنجاح'
        );
    }

    public function store(StorePaymentMethodRequest $request)
    {
        return $this->success(
            $this->paymentMethodService->create($request->validated()),
            'تم إنشاء طريقة الدفع بنجاح',
            201
        );
    }

    public function show(PaymentMethod $payment_method)
    {
        return $this->success($payment_method, 'تم جلب طريقة الدفع بنجاح');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $payment_method)
    {
        return $this->success(
            $this->paymentMethodService->update($payment_method, $request->validated()),
            'تم تحديث طريقة الدفع بنجاح'
        );
    }

    public function destroy(PaymentMethod $payment_method)
    {
        $this->paymentMethodService->delete($payment_method);

        return $this->success([], 'تم حذف طريقة الدفع بنجاح');
    }
}
