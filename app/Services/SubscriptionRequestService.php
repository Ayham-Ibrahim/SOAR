<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionRequestService
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function submitDirect(User $student, array $data): SubscriptionRequest
    {
        $request = SubscriptionRequest::create([
            'student_id' => $student->id,
            'course_id' => $data['course_id'],
            'receipt_image' => FileStorage::storeFile($data['receipt_image'], 'subscription-receipts', 'img'),
            'amount' => $data['amount'] ?? null,
            ...$this->paymentMethodFields($data['payment_method_id'] ?? null),
        ]);

        $this->notificationService->notifyAdminNewSubscriptionRequest($request);

        return $request;
    }

    public function submitOffer(User $student, array $data): SubscriptionRequest
    {
        $request = SubscriptionRequest::create([
            'student_id' => $student->id,
            'offer_id' => $data['offer_id'],
            'receipt_image' => FileStorage::storeFile($data['receipt_image'], 'subscription-receipts', 'img'),
            'amount' => $data['amount'] ?? null,
            ...$this->paymentMethodFields($data['payment_method_id'] ?? null),
        ]);

        $this->notificationService->notifyAdminNewSubscriptionRequest($request);

        return $request;
    }

    /**
     * The chosen method plus a copy of the account details the student was
     * shown, so the request keeps saying where the money went even if the
     * admin later edits or deletes that method. Validated active upstream.
     * None yet from app versions that predate payment methods.
     */
    private function paymentMethodFields(int|string|null $paymentMethodId): array
    {
        if ($paymentMethodId === null) {
            return [];
        }

        $method = PaymentMethod::findOrFail($paymentMethodId);

        return [
            'payment_method_id' => $method->id,
            'payment_details' => $method->only(['option_name', 'person_name', 'person_phone']),
        ];
    }

    public function listForStudent(User $student, int $perPage = 15): LengthAwarePaginator
    {
        return SubscriptionRequest::query()
            ->where('student_id', $student->id)
            ->with(['course', 'offer'])
            ->latest()
            ->paginate($perPage);
    }

    public function getForStudent(User $student, int $id): SubscriptionRequest
    {
        return SubscriptionRequest::query()
            ->where('student_id', $student->id)
            ->with(['course', 'offer'])
            ->findOrFail($id);
    }
}
