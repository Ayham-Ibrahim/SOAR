<?php

namespace App\Services\Admin;

use App\Models\Question;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionService
{
    public function list(?int $examId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Question::query()
            ->when($examId, fn ($query) => $query->where('exam_id', $examId))
            ->paginate($perPage);
    }

    public function create(array $data): Question
    {
        return Question::create([
            'exam_id' => $data['exam_id'],
            'text' => $data['text'],
            'points' => $data['points'] ?? 1,
            'order' => $data['order'] ?? 0,
        ]);
    }

    public function update(Question $question, array $data): Question
    {
        $question->update([
            'exam_id' => $data['exam_id'] ?? $question->exam_id,
            'text' => $data['text'] ?? $question->text,
            'points' => $data['points'] ?? $question->points,
            'order' => $data['order'] ?? $question->order,
        ]);

        return $question->fresh();
    }

    public function delete(Question $question): void
    {
        $question->delete();
    }
}
