<?php

namespace App\Services;

use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionRequestService
{
    public function submitDirect(User $student, array $data): SubscriptionRequest
    {
        return SubscriptionRequest::create([
            'student_id' => $student->id,
            'course_id' => $data['course_id'],
            'receipt_image' => FileStorage::storeFile($data['receipt_image'], 'subscription-receipts', 'img'),
            'amount' => $data['amount'] ?? null,
        ]);
    }

    public function submitOffer(User $student, array $data): SubscriptionRequest
    {
        return SubscriptionRequest::create([
            'student_id' => $student->id,
            'offer_id' => $data['offer_id'],
            'receipt_image' => FileStorage::storeFile($data['receipt_image'], 'subscription-receipts', 'img'),
            'amount' => $data['amount'] ?? null,
        ]);
    }

    public function listForStudent(User $student, int $perPage = 15): LengthAwarePaginator
    {
        return SubscriptionRequest::query()
            ->where('student_id', $student->id)
            ->with(['course', 'offer'])
            ->latest()
            ->paginate($perPage);
    }
}
