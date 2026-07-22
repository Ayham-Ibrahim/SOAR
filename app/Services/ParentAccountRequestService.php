<?php

namespace App\Services;

use App\Models\ParentAccountRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class ParentAccountRequestService
{
    public function submit(User $student, array $data): ParentAccountRequest
    {
        return ParentAccountRequest::create([
            'requested_by_student_id' => $student->id,
            'parent_name' => $data['parent_name'],
            'parent_phone' => $data['parent_phone'],
            'password' => Hash::make($data['password']),
        ]);
    }

    public function listForStudent(User $student, int $perPage = 15): LengthAwarePaginator
    {
        return ParentAccountRequest::query()
            ->where('requested_by_student_id', $student->id)
            ->latest()
            ->paginate($perPage);
    }
}
