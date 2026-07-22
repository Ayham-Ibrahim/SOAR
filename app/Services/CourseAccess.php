<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;

/**
 * The ONE access gate for paid course content. A student has access to a
 * course iff a subscriptions row exists for (student_id, course_id) with
 * expires_at > now() — that comparison is authoritative everywhere; the
 * subscriptions.is_active flag is a nightly-refreshed reporting field only,
 * never used here.
 */
class CourseAccess
{
    public function hasAccess(User $student, Course $course): bool
    {
        if ($student->is_admin) {
            return true;
        }

        return $student->subscriptions()
            ->where('course_id', $course->id)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
