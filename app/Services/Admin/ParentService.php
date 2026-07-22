<?php

namespace App\Services\Admin;

use App\Models\ParentModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class ParentService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return ParentModel::query()->latest()->paginate($perPage);
    }

    public function create(array $data): ParentModel
    {
        return ParentModel::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'phone_verified_at' => now(),
        ]);
    }

    public function update(ParentModel $parent, array $data): ParentModel
    {
        $parent->update([
            'name' => $data['name'] ?? $parent->name,
            'phone' => $data['phone'] ?? $parent->phone,
            'password' => isset($data['password']) ? Hash::make($data['password']) : $parent->password,
        ]);

        return $parent->fresh();
    }

    public function delete(ParentModel $parent): void
    {
        $parent->tokens()->delete();
        $parent->delete();
    }

    /**
     * Add students to this parent without detaching any existing links —
     * removal is a separate, explicit action (unlinkStudent).
     */
    public function attachStudents(ParentModel $parent, array $studentIds): ParentModel
    {
        $parent->students()->syncWithoutDetaching($studentIds);

        return $parent->fresh('students');
    }

    public function unlinkStudent(ParentModel $parent, int $studentId): void
    {
        $parent->students()->detach($studentId);
    }
}
