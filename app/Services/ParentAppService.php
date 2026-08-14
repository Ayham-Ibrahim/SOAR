<?php

namespace App\Services;

use App\Models\ParentModel;
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
    public function children(ParentModel $parent): Collection
    {
        return $parent->students()->get(['users.id', 'users.name', 'users.phone', 'users.avatar']);
    }

    public function subscriptionsForStudent(ParentModel $parent, User $student): SupportCollection
    {
        if (! $parent->students()->where('users.id', $student->id)->exists()) {
            return collect();
        }

        return Subscription::query()
            ->where('student_id', $student->id)
            ->where('expires_at', '>', now())
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
                    'price' => (float) ($course?->price ?? $offer?->price ?? 0),
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
            ->where('expires_at', '>', now())
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
}
