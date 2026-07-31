<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\ParentModel;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Send Broadcast Notification Job
 *
 * Handles sending notifications to students and parents based on the admin's
 * filters and target recipient type.
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
        } catch (\Exception $e) {
            $this->notification->markAsFailed();

            Log::error("Failed to send broadcast notification", [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send notification to students only.
     */
    private function sendToStudents(FcmService $fcmService): int
    {
        $query = User::query();

        $filters = $this->notification->filters ?? [];

        $query
            ->when(! empty(Arr::get($filters, 'governorate_id')), function ($query) use ($filters) {
                $query->where('governorate_id', (int) $filters['governorate_id']);
            })
            ->when(! empty(Arr::get($filters, 'gender')), function ($query) use ($filters) {
                $query->where('gender', (string) $filters['gender']);
            })
            ->when(! empty(Arr::get($filters, 'student_ids')), function ($query) use ($filters) {
                $query->whereIn('id', Arr::wrap($filters['student_ids']));
            });

        $tokens = $query->get()->flatMap(function (User $user) {
            return $user->devices()->whereNotNull('fcm_token')->pluck('fcm_token');
        })->filter()->unique()->values()->toArray();

        return $this->sendBatchNotifications($fcmService, $tokens);
    }

    /**
     * Send notification to parents only.
     *
     * For parents, the governorate/gender filters are applied against the linked
     * students, not the parent record itself.
     */
    private function sendToParents(FcmService $fcmService): int
    {
        $query = ParentModel::query();

        $filters = $this->notification->filters ?? [];

        $query
            ->when(! empty(Arr::get($filters, 'parent_ids')), function ($query) use ($filters) {
                $query->whereIn('id', Arr::wrap($filters['parent_ids']));
            });

        $studentFilters = [];

        if (! empty(Arr::get($filters, 'governorate_id'))) {
            $studentFilters['governorate_id'] = (int) $filters['governorate_id'];
        }

        if (! empty(Arr::get($filters, 'gender'))) {
            $studentFilters['gender'] = (string) $filters['gender'];
        }

        if (! empty(Arr::get($filters, 'student_ids'))) {
            $studentFilters['student_ids'] = Arr::wrap($filters['student_ids']);
        }

        $query->when(! empty($studentFilters), function ($query) use ($studentFilters) {
            $query->whereHas('students', function ($studentQuery) use ($studentFilters) {
                $studentQuery
                    ->when(isset($studentFilters['governorate_id']), function ($studentQuery) use ($studentFilters) {
                        $studentQuery->where('governorate_id', $studentFilters['governorate_id']);
                    })
                    ->when(isset($studentFilters['gender']), function ($studentQuery) use ($studentFilters) {
                        $studentQuery->where('gender', $studentFilters['gender']);
                    })
                    ->when(! empty($studentFilters['student_ids'] ?? []), function ($studentQuery) use ($studentFilters) {
                        $studentQuery->whereIn('users.id', $studentFilters['student_ids']);
                    });
            });
        });

        $tokens = $query->get()->flatMap(function (ParentModel $parent) {
            return $parent->devices()->whereNotNull('fcm_token')->pluck('fcm_token');
        })->filter()->unique()->values()->toArray();

        return $this->sendBatchNotifications($fcmService, $tokens);
    }

    /**
     * Send notification to all users and parents.
     */
    private function sendToAll(FcmService $fcmService): int
    {
        $studentTokens = $this->sendToStudents($fcmService);
        $parentTokens = $this->sendToParents($fcmService);

        return $studentTokens + $parentTokens;
    }

    /**
     * Send notifications in batches.
     */
    private function sendBatchNotifications(FcmService $fcmService, array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        // Process in chunks of 500 to avoid memory issues
        $chunks = array_chunk($tokens, 500);
        $totalSent = 0;

        foreach ($chunks as $chunk) {
            try {
                $result = $fcmService->sendToMultipleTokens(
                    $chunk,
                    $this->notification->title,
                    $this->notification->content,
                    [
                        'type' => 'broadcast',
                        'notification_id' => (string) $this->notification->id,
                    ]
                );

                $totalSent += $result['success'];

                // Update sent count incrementally
                $this->notification->incrementSentCount($result['success']);
            } catch (\Exception $e) {
                Log::error("Failed to send notification batch", [
                    'notification_id' => $this->notification->id,
                    'batch_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalSent;
    }
}
