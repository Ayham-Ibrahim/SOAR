<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class StudentService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()->latest()->paginate($perPage);
    }

    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'gender' => $data['gender'] ?? null,
            'age' => $data['age'] ?? null,
            'avatar' => isset($data['avatar']) ? FileStorage::storeFile($data['avatar'], 'avatars', 'img') : null,
            'password' => Hash::make($data['password']),
            'phone_verified_at' => now(),
            'governorate_id' => $data['governorate_id'] ?? null,
            'school_id' => $data['school_id'] ?? null,
        ]);
    }

    public function update(User $student, array $data): User
    {
        $student->update([
            'name' => $data['name'] ?? $student->name,
            'phone' => $data['phone'] ?? $student->phone,
            'email' => $data['email'] ?? $student->email,
            'gender' => $data['gender'] ?? $student->gender,
            'age' => $data['age'] ?? $student->age,
            'avatar' => isset($data['avatar'])
                ? FileStorage::fileExists($data['avatar'], $student->avatar, 'avatars', 'img')
                : $student->avatar,
            'password' => isset($data['password']) ? Hash::make($data['password']) : $student->password,
            'governorate_id' => $data['governorate_id'] ?? $student->governorate_id,
            'school_id' => $data['school_id'] ?? $student->school_id,
        ]);

        return $student->fresh();
    }

    public function delete(User $student): void
    {
        $student->tokens()->delete();
        $student->delete();
    }
}
