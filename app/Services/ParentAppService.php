<?php

namespace App\Services;

use App\Models\ParentModel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Course;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Self-service actions for an authenticated parent account (the "Parent
 * app" side), as opposed to Services\Admin\ParentService which is the
 * admin dashboard's CRUD over the parents resource.
 */
class ParentAppService
{
    public function children(ParentModel $parent, ?string $search = null): Collection
    {
        return $parent->students()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.phone', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhere('users.id', (int) $search);
                    }
                });
            })
            ->get(['users.id', 'users.name', 'users.phone', 'users.avatar']);
    }

    public function subscriptionsForStudent(ParentModel $parent, User $student): SupportCollection
    {
        if (! $parent->students()->where('users.id', $student->id)->exists()) {
            return collect();
        }

        return Subscription::query()
            ->where('student_id', $student->id)
            ->active()
            ->with([
                'course.teacher',
                'offer.courses.teacher',
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(function (Subscription $subscription) {
                $course = $subscription->course;
                $offer = $subscription->offer;

                return [
                    'id' => $subscription->id,
                    'student_id' => $subscription->student_id,
                    'course_id' => $subscription->course_id,
                    'offer_id' => $subscription->offer_id,
                    'source' => $subscription->source,
                    'starts_at' => $subscription->starts_at?->toDateTimeString(),
                    'expires_at' => $subscription->expires_at?->toDateTimeString(),
                    'teacher_name' => $course?->teacher?->name,
                    'price' => (float) ($offer?->price ?? $course?->price ??  0),
                    'course' => $course ? [
                        'id' => $course->id,
                        'title' => $course->title,
                        'price' => (float) ($course->price ?? 0),
                        'teacher' => $course->teacher ? [
                            'id' => $course->teacher->id,
                            'name' => $course->teacher->name,
                        ] : null,
                    ] : null,
                    'offer' => $offer ? [
                        'id' => $offer->id,
                        'title' => $offer->title,
                        'description' => $offer->description,
                        'price' => (float) ($offer->price ?? 0),
                        'offer_starts_at' => $offer->offer_starts_at?->toDateTimeString(),
                        'offer_ends_at' => $offer->offer_ends_at?->toDateTimeString(),
                        'access_duration_days' => $offer->access_duration_days,
                        'courses' => $offer->courses?->map(function ($courseItem) {
                            return [
                                'id' => $courseItem->id,
                                'title' => $courseItem->title,
                                'teacher' => $courseItem->teacher ? [
                                    'id' => $courseItem->teacher->id,
                                    'name' => $courseItem->teacher->name,
                                ] : null,
                            ];
                        })->values()->all() ?? [],
                    ] : null,
                ];
            });
    }

    public function offersForStudent(ParentModel $parent, User $student): SupportCollection
    {
        if (! $parent->students()->where('users.id', $student->id)->exists()) {
            return collect();
        }

        return Subscription::query()
            ->where('student_id', $student->id)
            ->active()
            ->whereNotNull('offer_id')
            ->with(['offer.courses.teacher'])
            ->get()
            ->groupBy('offer_id')
            ->map(function ($rows) {
                $offer = $rows->first()->offer;

                return [
                    'id' => $offer?->id,
                    'title' => $offer?->title,
                    'description' => $offer?->description,
                    'price' => (float) ($offer?->price ?? 0),
                    'offer_starts_at' => $offer?->offer_starts_at?->toDateTimeString(),
                    'offer_ends_at' => $offer?->offer_ends_at?->toDateTimeString(),
                    'access_duration_days' => $offer?->access_duration_days,
                    'courses' => $offer?->courses?->map(function ($course) {
                        return [
                            'id' => $course->id,
                            'title' => $course->title,
                            'teacher' => $course->teacher ? [
                                'id' => $course->teacher->id,
                                'name' => $course->teacher->name,
                            ] : null,
                        ];
                    })->values()->all() ?? [],
                ];
            })
            ->values();
    }

    /**
     * The child's academic record grouped by subject (in the subjects' own
     * order): every course they are or were subscribed to, or sat an exam
     * in, each with the exams that concern them and their own results.
     *
     * An exam concerns the student if they attempted it — kept even if it
     * was deactivated since, so a result never vanishes — or if it's active
     * in a course they still have a live subscription to (open to take).
     * Other students' attempts and unrelated courses are never loaded.
     */
    public function academicDetailsForStudent(ParentModel $parent, User $student): SupportCollection
    {
        if (! $parent->students()->where('users.id', $student->id)->exists()) {
            return collect();
        }

        $attemptedExamIds = ExamAttempt::query()
            ->where('user_id', $student->id)
            ->distinct()
            ->pluck('exam_id');

        return Course::query()
            ->where(fn ($query) => $query
                ->whereHas('subscriptions', fn ($subscriptions) => $subscriptions->where('student_id', $student->id))
                ->orWhereHas('exams', fn ($exams) => $exams->whereIn('exams.id', $attemptedExamIds)))
            ->with([
                'subject' => fn ($query) => $query->withTrashed(),
                'teacher',
                'subscriptions' => fn ($query) => $query->where('student_id', $student->id),
                'exams' => fn ($query) => $query
                    ->where(fn ($exams) => $exams->where('is_active', true)->orWhereIn('exams.id', $attemptedExamIds))
                    ->orderBy('id')
                    ->with(['attempts' => fn ($attempts) => $attempts->where('user_id', $student->id)->latest()]),
            ])
            ->orderBy('title')
            ->get()
            ->groupBy('subject_id')
            ->sortBy(fn ($courses) => $courses->first()->subject?->order ?? PHP_INT_MAX)
            ->map(function ($courses) {
                $subject = $courses->first()->subject;

                return [
                    'subject' => $subject ? [
                        'id' => $subject->id,
                        'name' => $subject->name,
                    ] : null,
                    'courses' => $courses->map(fn (Course $course) => $this->academicCourse($course))->values()->all(),
                ];
            })
            ->values();
    }

    private function academicCourse(Course $course): array
    {
        // The live grant if there is one, else the latest (expired or revoked) one.
        $subscription = $course->subscriptions->first(fn (Subscription $grant) => $grant->isActive())
            ?? $course->subscriptions->sortByDesc('expires_at')->first();
        $hasAccess = (bool) $subscription?->isActive();

        return [
            'id' => $course->id,
            'title' => $course->title,
            'teacher_name' => $course->teacher?->name,
            'starts_at' => $subscription?->starts_at?->toDateTimeString(),
            'expires_at' => $subscription?->expires_at?->toDateTimeString(),
            'is_subscription_active' => $hasAccess,
            'subscription_status' => $subscription?->status, // active | expired | revoked, null if never subscribed
            'exams' => $course->exams
                ->filter(fn (Exam $exam) => $exam->attempts->isNotEmpty() || $hasAccess)
                ->map(fn (Exam $exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'type' => $exam->type,
                    'description' => $exam->description,
                    'duration_minutes' => $exam->duration_minutes,
                    'total_score' => $exam->total_score,
                    'passing_score' => $exam->passing_score,
                    'attempts' => $exam->attempts->map(fn (ExamAttempt $attempt) => [
                        'id' => $attempt->id,
                        'status' => $attempt->status,
                        'score' => $attempt->score,
                        'passed' => $this->hasPassed($exam, $attempt),
                        'total_questions' => $attempt->total_questions,
                        'correct_answers' => $attempt->correct_answers,
                        'earned_points' => $attempt->earned_points,
                        'total_points' => $attempt->total_points,
                        'time_spent_seconds' => $attempt->time_spent_seconds,
                        'submitted_at' => $attempt->created_at?->toDateTimeString(),
                        'graded_at' => $attempt->graded_at?->toDateTimeString(),
                        'feedback' => $attempt->feedback,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Null while a written exam awaits review, or when the exam has no
     * passing score set. score is already on the exam's total_score scale.
     */
    private function hasPassed(Exam $exam, ExamAttempt $attempt): ?bool
    {
        if ($attempt->status !== 'graded' || $attempt->score === null || $exam->passing_score === null) {
            return null;
        }

        return (float) $attempt->score >= $exam->passing_score;
    }
}
