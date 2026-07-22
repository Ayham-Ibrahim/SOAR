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
        return ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'status' => 'pending_review',
            'submission_file' => FileStorage::storeFile($data['submission_file'], 'exam-submissions', 'docs'),
        ]);
    }

    private function submitMcq(Exam $exam, User $user, array $data): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user, $data) {
            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'status' => 'graded',
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
                'score' => $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0,
            ]);

            return $attempt->fresh('answers');
        });
    }

    public function listForUser(User $user, ?int $examId, int $perPage = 15): LengthAwarePaginator
    {
        return ExamAttempt::where('user_id', $user->id)
            ->with('exam')
            ->when($examId, fn ($query) => $query->where('exam_id', $examId))
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
        $attempt->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        return $attempt->fresh();
    }
}
