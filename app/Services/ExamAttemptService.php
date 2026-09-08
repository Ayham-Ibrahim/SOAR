<?php

namespace App\Services;

use App\Models\Choice;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExamAttemptService
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * Submit an attempt for the given user. MCQ exams are graded immediately;
     * written exams are stored as a file submission pending manual review.
     */
    public function submit(User $user, array $data): ExamAttempt
    {
        $exam = Exam::findOrFail($data['exam_id']);

        return $exam->type === 'written'
            ? $this->submitWritten($exam, $user, $data)
            : $this->submitMcq($exam, $user, $data);
    }

    private function submitWritten(Exam $exam, User $user, array $data): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user, $data) {
            $isFirstAttempt = $this->lockStudentAndCheckFirstAttempt($exam, $user);
            $submissionFiles = $this->storeWrittenAttachments($data['submission_files']);
            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'status' => 'pending_review',
                'time_spent_seconds' => $data['time_spent_seconds'] ?? null,
                'submission_files' => $submissionFiles,
            ]);

            $this->notificationService->notifyAdminWrittenExamSubmitted(
                $attempt->fresh(['exam', 'user'])
            );

            return $attempt;
        });
    }

    /**
     * The attachment may be a PDF or an image — FileStorage validates each
     * against a different allowed-type list, so route by the file's mime.
     */
    private function storeAttachment($file, ?string $old = null): string
    {
        $suffix = str_starts_with($file->getMimeType(), 'image/') ? 'img' : 'docs';

        return $old
            ? FileStorage::fileExists($file, $old, 'exam-submissions', $suffix)
            : FileStorage::storeFile($file, 'exam-submissions', $suffix);
    }

    /**
    * Store all written-exam images under the same attempt.
     *
     * @return array<int, string>
     */
    private function storeWrittenAttachments(array $files): array
    {
        return array_map(
            fn ($file) => $this->storeAttachment($file),
            array_values($files)
        );
    }

    private function submitMcq(Exam $exam, User $user, array $data): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user, $data) {
            $isFirstAttempt = $this->lockStudentAndCheckFirstAttempt($exam, $user);
            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'status' => 'graded',
                'time_spent_seconds' => $data['time_spent_seconds'] ?? null,
                'graded_at' => now(),
            ]);

            $total = count($data['answers']);
            $correct = 0;
            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($data['answers'] as $answer) {
                $choice = Choice::find($answer['choice_id']);
                $question = Question::find($answer['question_id']);
                $isCorrect = $choice
                    && $choice->is_correct
                    && $choice->question_id === (int) $answer['question_id'];

                if ($isCorrect) {
                    $correct++;
                }

                $points = $question?->points ?? 1;
                $totalPoints += $points;
                $earnedPoints += $isCorrect ? $points : 0;

                $attempt->answers()->create([
                    'question_id' => $answer['question_id'],
                    'choice_id' => $answer['choice_id'],
                    'is_correct' => $isCorrect,
                ]);
            }

            $attempt->update([
                'total_questions' => $total,
                'correct_answers' => $correct,
                'total_points' => $totalPoints,
                'earned_points' => $earnedPoints,
                'score' => $this->calculateScore($exam, $earnedPoints, $totalPoints),
            ]);

            if ($isFirstAttempt) {
                $this->notificationService->notifyExamResult($attempt->fresh(['exam', 'user']));
            }

            return $attempt->fresh(['exam', 'answers']);
        });
    }

    public function listForUser(User $user, ?int $examId, int $perPage = 15): LengthAwarePaginator
    {
        return ExamAttempt::where('user_id', $user->id)
            ->with('exam')
            ->when($examId, fn($query) => $query->where('exam_id', $examId))
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(User $user, int $id): ExamAttempt
    {
        return ExamAttempt::where('user_id', $user->id)
            ->with(['exam', 'answers.question', 'answers.choice'])
            ->findOrFail($id);
    }

    public function grade(ExamAttempt $attempt, array $data): ExamAttempt
    {
        $wasPendingReview = $attempt->status === 'pending_review';
        $isFirstAttempt = ! ExamAttempt::query()
            ->where('exam_id', $attempt->exam_id)
            ->where('user_id', $attempt->user_id)
            ->where('id', '<>', $attempt->id)
            ->exists();
        $attempt->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        if ($wasPendingReview && $isFirstAttempt) {
            $this->notificationService->notifyExamResult($attempt->fresh(['exam', 'user.parents']));
        }

        return $attempt->fresh();
    }

    private function calculateScore(Exam $exam, int $earnedPoints, int $totalPoints): float
    {
        if ($totalPoints <= 0) {
            return 0;
        }

        return round(($earnedPoints / $totalPoints) * ($exam->total_score ?? 100), 2);
    }

    private function lockStudentAndCheckFirstAttempt(Exam $exam, User $user): bool
    {
        User::query()->whereKey($user->id)->lockForUpdate()->first();

        return ! ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->exists();
    }

}
