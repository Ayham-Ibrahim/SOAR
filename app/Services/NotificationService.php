<?php

namespace App\Services;

use App\Models\ParentAccountRequest;
use App\Models\ParentModel;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function __construct(protected FcmService $fcmService) {}

    public function notifyAdminNewParentAccountRequest(ParentAccountRequest $request): int
    {
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return 0;
        }

        $studentName = $request->student ? $request->student->name : 'طالب';

        return $this->sendToUsers(
            $admins,
            'طلب حساب ولي أمر جديد',
            "تم تقديم طلب إنشاء حساب ولي أمر من الطالب {$studentName}.",
            [
                'type' => 'parent_account_request',
                'request_id' => (string) $request->id,
            ]
        );
    }

    public function notifyStudentParentAccountApproved(User $student, ParentModel $parent): int
    {
        $studentName = $student->name ?? 'الطالب';

        return $this->fcmService->sendToUser(
            $student,
            'تمت الموافقة على طلب ولي الأمر',
            "تمت الموافقة على طلبك لإنشاء حساب ولي أمر، وتم ربط الطالب {$studentName} مع ولي الأمر {$parent->name}.",
            [
                'type' => 'parent_account_approved',
                'parent_id' => (string) $parent->id,
            ]
        );
    }

    public function notifyParentAccountApproved(ParentModel $parent, User $student): int
    {
        return $this->fcmService->sendToParent(
            $parent,
            'تمت الموافقة على الحساب',
            "تمت الموافقة على طلبك وربطك مع الطالب {$student->name}.",
            [
                'type' => 'parent_account_approved',
                'student_id' => (string) $student->id,
            ]
        );
    }

    public function notifyStudentParentAccountRejected(User $student, string $reason): int
    {
        return $this->fcmService->sendToUser(
            $student,
            'تم رفض طلب ولي الأمر',
            $reason !== '' ? "تم رفض طلبك: {$reason}" : 'تم رفض طلبك لإنشاء حساب ولي أمر. يرجى مراجعة البيانات والمحاولة مرة أخرى.',
            [
                'type' => 'parent_account_rejected',
            ]
        );
    }

    public function notifyAdminNewSubscriptionRequest(SubscriptionRequest $request): int
    {
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return 0;
        }

        $studentName = $request->student ? $request->student->name : 'طالب';
        $title = $request->course_id ? 'طلب اشتراك لدورة جديدة' : 'طلب اشتراك باقة جديدة';
        $courseTitle = $request->course ? $request->course->title : 'المحددة';
        $offerTitle = $request->offer ? $request->offer->title : 'المحددة';
        $body = $request->course_id
            ? "تم تقديم طلب اشتراك جديد من الطالب {$studentName} للدورة {$courseTitle}."
            : "تم تقديم طلب اشتراك جديد من الطالب {$studentName} للباقة {$offerTitle}.";

        return $this->sendToUsers(
            $admins,
            $title,
            $body,
            [
                'type' => 'subscription_request',
                'request_id' => (string) $request->id,
            ]
        );
    }

    public function notifyStudentSubscriptionApproved(User $student, SubscriptionRequest $request): int
    {
        $label = $request->course_id
            ? ($request->course ? $request->course->title : 'الدورة')
            : ($request->offer ? $request->offer->title : 'الباقة');

        return $this->fcmService->sendToUser(
            $student,
            'تمت الموافقة على اشتراكك',
            "تمت الموافقة على طلب اشتراكك في {$label}.",
            [
                'type' => 'subscription_approved',
                'request_id' => (string) $request->id,
            ]
        );
    }

    public function notifyStudentSubscriptionRejected(User $student, string $reason): int
    {
        return $this->fcmService->sendToUser(
            $student,
            'تم رفض طلب اشتراكك',
            $reason !== '' ? "تم رفض طلب اشتراكك: {$reason}" : 'تم رفض طلب اشتراكك. يرجى مراجعة البيانات والمحاولة مرة أخرى.',
            [
                'type' => 'subscription_rejected',
            ]
        );
    }

    public function notifyStudentSubscriptionExpired(User $student, Subscription $subscription): int
    {
        $label = $subscription->course
            ? $subscription->course->title
            : ($subscription->offer ? $subscription->offer->title : 'اشتراكك');

        return $this->fcmService->sendToUser(
            $student,
            'انتهت صلاحية اشتراكك',
            "انتهت صلاحية اشتراكك في {$label}. يمكنك تجديده من خلال التطبيق.",
            [
                'type' => 'subscription_expired',
                'subscription_id' => (string) $subscription->id,
            ]
        );
    }

    public function notiParentAccountRequestApprovedForStudent(User $student, ParentModel $parent): int
    {
        return $this->notifyStudentParentAccountApproved($student, $parent);
    }

    public function notiParentAccountRequestRejectedForStudent(User $student, string $reason): int
    {
        return $this->notifyStudentParentAccountRejected($student, $reason);
    }

    public function notiSubscriptionRequestApprovedForStudent(User $student, SubscriptionRequest $request): int
    {
        return $this->notifyStudentSubscriptionApproved($student, $request);
    }

    public function notiSubscriptionRequestRejectedForStudent(User $student, string $reason): int
    {
        return $this->notifyStudentSubscriptionRejected($student, $reason);
    }

    public function notiSubscriptionExpiredForStudent(User $student, Subscription $subscription): int
    {
        return $this->notifyStudentSubscriptionExpired($student, $subscription);
    }

    protected function sendToUsers(Collection $users, string $title, string $body, array $data = []): int
    {
        $sent = 0;

        foreach ($users as $user) {
            if ($user instanceof User) {
                $sent += $this->fcmService->sendToUser($user, $title, $body, $data);
            }
        }

        return $sent;
    }
}
