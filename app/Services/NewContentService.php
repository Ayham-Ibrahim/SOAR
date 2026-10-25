<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\News;
use App\Models\SeenMarker;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The app's "new" dots on news and exam results. Something is new to a
 * viewer if it appeared after they last opened that section (markSeen);
 * before their first visit, after their account was created — so a new
 * account doesn't start with the whole archive flagged.
 *
 * Results are per student: a student's own, or each child of a parent,
 * tracked separately for that parent.
 */
class NewContentService
{
    public function newNewsCount(Model $viewer): int
    {
        return News::query()
            ->where('is_active', true)
            ->where('created_at', '>', $this->lastSeen($viewer, SeenMarker::NEWS))
            ->count();
    }

    /**
     * Results that came out (graded) since the viewer last looked: an MCQ
     * attempt on submission, a written one when the admin grades it.
     */
    public function newResultsCount(Model $viewer, User $student): int
    {
        return ExamAttempt::query()
            ->where('user_id', $student->id)
            ->where('status', 'graded')
            ->where('graded_at', '>', $this->lastSeen($viewer, SeenMarker::RESULTS, $this->studentKey($viewer, $student)))
            ->count();
    }

    public function markSeen(Model $viewer, string $section, ?User $student = null): void
    {
        SeenMarker::updateOrCreate(
            [
                'viewer_type' => $viewer->getMorphClass(),
                'viewer_id' => $viewer->getKey(),
                'section' => $section,
                'student_id' => $student ? $this->studentKey($viewer, $student) : 0,
            ],
            ['seen_at' => now()]
        );
    }

    /**
     * @return array{new_count: int, has_new: bool}
     */
    public function summary(int $count): array
    {
        return ['new_count' => $count, 'has_new' => $count > 0];
    }

    private function lastSeen(Model $viewer, string $section, int $studentId = 0): Carbon
    {
        $seenAt = SeenMarker::query()
            ->where('viewer_type', $viewer->getMorphClass())
            ->where('viewer_id', $viewer->getKey())
            ->where('section', $section)
            ->where('student_id', $studentId)
            ->value('seen_at');

        return $seenAt ? Carbon::parse($seenAt) : ($viewer->created_at ?? Carbon::createFromTimestamp(0));
    }

    /**
     * A student's own results marker isn't keyed by student; a parent's is
     * keyed by the child.
     */
    private function studentKey(Model $viewer, User $student): int
    {
        return $viewer->is($student) ? 0 : $student->id;
    }
}
