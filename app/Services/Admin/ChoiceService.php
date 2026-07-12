<?php

namespace App\Services\Admin;

use App\Models\Choice;
use Illuminate\Pagination\LengthAwarePaginator;

class ChoiceService
{
    public function list(?int $questionId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Choice::query()
            ->when($questionId, fn ($query) => $query->where('question_id', $questionId))
            ->paginate($perPage);
    }

    public function create(array $data): Choice
    {
        return Choice::create([
            'question_id' => $data['question_id'],
            'text' => $data['text'],
            'is_correct' => $data['is_correct'] ?? false,
            'order' => $data['order'] ?? 0,
        ]);
    }

    public function update(Choice $choice, array $data): Choice
    {
        $choice->update([
            'question_id' => $data['question_id'] ?? $choice->question_id,
            'text' => $data['text'] ?? $choice->text,
            'is_correct' => $data['is_correct'] ?? $choice->is_correct,
            'order' => $data['order'] ?? $choice->order,
        ]);

        return $choice->fresh();
    }

    public function delete(Choice $choice): void
    {
        $choice->delete();
    }
}
