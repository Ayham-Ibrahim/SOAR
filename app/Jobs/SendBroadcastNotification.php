<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\ParentModel;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Send Broadcast Notification Job
 *
 * Sends a notification to students and/or parents picked by the admin's
 * classification filters. Every recipient gets a stored notification for
 * their in-app inbox, plus a push to each device they have registered.
 *
 * The counts returned here (and sent_count) are RECIPIENTS, not devices: a
 * parent with no device still received the notification in the app.
 */
class SendBroadcastNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Notification $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService): void
    {
        try {
            $this->notification->markAsSending();

            $totalSent = 0;

            foreach ($this->notification->target_types as $targetType) {
                $sent = match ($targetType) {
                    Notification::TARGET_STUDENTS => $this->sendToStudents($fcmService),
                    Notification::TARGET_PARENTS => $this->sendToParents($fcmService),
                    Notification::TARGET_ALL => $this->sendToAll($fcmService),
                    default => 0,
                };

                $totalSent += $sent;
            }

            $this->notification->markAsCompleted($totalSent);

            Log::info("Broadcast notification sent successfully", [
                'notification_id' => $this->notification->id,
                'total_sent' => $totalSent,
            ]);
        } catch (\Throwable $e) {
            $this->notification->markAsFailed();

            Log::error("Failed to send broadcast notification", [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send to the students matching every filter given (a filter left out
     * doesn't narrow anything, so no filters at all means all students).
     *
     * @return int recipients
     */
    private function sendToStudents(FcmService $fcmService): int
    {
        $students = User::query()
            ->where('is_admin', false)
            ->tap(fn (Builder $query) => $this->applyStudentFilters($query, $this->studentFilters()))
            ->get();

        foreach ($students as $student) {
            $fcmService->sendToUser(
                $student,
                $this->notification->title,
                $this->notification->content,
                $this->payload()
            );
        }

        return $students->count();
    }

    /**
     * Send to parents. The classification filters describe STUDENTS, so they
     * are matched against the parent's linked children, while parent_ids picks
     * parents outright. The two are a union: "these parents" plus "parents of
     * third-graders" reaches both groups, rather than only parents in both.
     *
     * @return int recipients
     */
    private function sendToParents(FcmService $fcmService): int
    {
        $parentIds = array_values(Arr::wrap(Arr::get($this->notification->filters ?? [], 'parent_ids', [])));
        $studentFilters = $this->studentFilters();

        $parents = ParentModel::query()
            ->when(! empty($parentIds) || ! empty($studentFilters), function (Builder $query) use ($parentIds, $studentFilters) {
                $query->where(function (Builder $query) use ($parentIds, $studentFilters) {
                    if (! empty($parentIds)) {
                        $query->orWhereIn('id', $parentIds);
                    }

                    if (! empty($studentFilters)) {
                        $query->orWhereHas('students', fn (Builder $students) => $this->applyStudentFilters($students, $studentFilters));
                    }
                });
            })
            ->get();

        foreach ($parents as $parent) {
            $fcmService->sendToParent(
                $parent,
                $this->notification->title,
                $this->notification->content,
                $this->payload()
            );
        }

        return $parents->count();
    }

    /**
     * Send notification to all users and parents.
     *
     * @return int recipients
     */
    private function sendToAll(FcmService $fcmService): int
    {
        return $this->sendToStudents($fcmService) + $this->sendToParents($fcmService);
    }

    /**
     * The filters that describe a student, dropping the ones not chosen.
     */
    private function studentFilters(): array
    {
        $filters = $this->notification->filters ?? [];

        return array_filter([
            'governorate_id' => Arr::get($filters, 'governorate_id'),
            'category_id' => Arr::get($filters, 'category_id'),
            'sub_category_id' => Arr::get($filters, 'sub_category_id'),
            'study_type_id' => Arr::get($filters, 'study_type_id'),
            'gender' => Arr::get($filters, 'gender'),
            'student_ids' => array_values(Arr::wrap(Arr::get($filters, 'student_ids', []))),
        ], fn ($value) => ! empty($value));
    }

    /**
     * Columns are qualified because this also runs inside the parents'
     * whereHas on the students relation.
     */
    private function applyStudentFilters(Builder $query, array $filters): void
    {
        $query
            ->when(isset($filters['governorate_id']), fn (Builder $query) => $query->where('users.governorate_id', (int) $filters['governorate_id']))
            ->when(isset($filters['category_id']), fn (Builder $query) => $query->where('users.category_id', (int) $filters['category_id']))
            ->when(isset($filters['sub_category_id']), fn (Builder $query) => $query->where('users.sub_category_id', (int) $filters['sub_category_id']))
            ->when(isset($filters['study_type_id']), fn (Builder $query) => $query->where('users.study_type_id', (int) $filters['study_type_id']))
            ->when(isset($filters['gender']), fn (Builder $query) => $query->where('users.gender', (string) $filters['gender']))
            ->when(! empty($filters['student_ids']), fn (Builder $query) => $query->whereIn('users.id', $filters['student_ids']));
    }

    private function payload(): array
    {
        return [
            'type' => $this->notification->filters['notification_type'] ?? 'broadcast',
            'notification_id' => (string) $this->notification->id,
        ];
    }
}
