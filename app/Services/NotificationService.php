<?php

namespace App\Services;

use App\Models\ParentAccountRequest;
use App\Models\ParentModel;
use App\Models\ExamAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

        return $this->sendToRecipient(
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
        return $this->sendToRecipient(
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
        return $this->sendToRecipient(
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

    public function notifyExamResult(ExamAttempt $attempt): int
    {
        $attempt->loadMissing(['exam', 'user.parents']);
        $studentName = $attempt->user?->name ?? 'الطالب';
        $examTitle = $attempt->exam?->title ?? 'الامتحان';
        $score = $attempt->score !== null ? (string) $attempt->score : 'قيد التصحيح';
        $title = 'نتيجة امتحان جديدة';
        $body = "تقدم الطالب {$studentName} للامتحان {$examTitle}، والنتيجة: {$score}.";
        $data = [
            'type' => 'exam_result',
            'exam_id' => (string) $attempt->exam_id,
            'attempt_id' => (string) $attempt->id,
            'student_id' => (string) $attempt->user_id,
        ];

        $sent = $this->sendToUsers(
            User::query()->where('is_admin', true)->get(),
            $title,
            $body,
            $data
        );

        foreach ($attempt->user?->parents ?? [] as $parent) {
            $sent += $this->sendToRecipient($parent, $title, $body, $data);
        }

        return $sent;
    }

    public function notifyStudentSubscriptionApproved(User $student, SubscriptionRequest $request): int
    {
        $label = $request->course_id
            ? ($request->course ? $request->course->title : 'الدورة')
            : ($request->offer ? $request->offer->title : 'الباقة');

        return $this->sendToRecipient(
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
        return $this->sendToRecipient(
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

        return $this->sendToRecipient(
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
                $sent += $this->sendToRecipient($user, $title, $body, $data);
            }
        }

        return $sent;
    }

    protected function sendToRecipient(Model $recipient, string $title, string $body, array $data = []): int
    {
        return $recipient instanceof ParentModel
            ? $this->fcmService->sendToParent($recipient, $title, $body, $data)
            : $this->fcmService->sendToUser($recipient, $title, $body, $data);
    }
}
