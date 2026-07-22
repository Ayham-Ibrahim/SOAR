<?php

namespace App\Services\Admin;

use App\Models\ParentAccountRequest;
use App\Models\ParentModel;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ParentAccountRequestService
{
    public function list(?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return ParentAccountRequest::query()
            ->with('student:id,name,phone')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create the parent account from the request's stored hash (no
     * re-hashing), link every given student plus the original requester,
     * and mark the request approved — all inside one transaction.
     */
    public function approve(ParentAccountRequest $request, array $studentIds, User $admin): ParentModel
    {
        return DB::transaction(function () use ($request, $studentIds, $admin) {
            $parent = ParentModel::create([
                'name' => $request->parent_name,
                'phone' => $request->parent_phone,
                'password' => $request->password,
                'phone_verified_at' => now(),
            ]);

            if (! in_array($request->requested_by_student_id, $studentIds, true)) {
                $studentIds[] = $request->requested_by_student_id;
            }

            $parent->students()->attach($studentIds);

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'created_parent_id' => $parent->id,
            ]);

            // TODO: notify the requesting student (User::notify) once a concrete
            // notification channel exists for this project — none does yet
            // (no App\Notifications classes, no notifications table).

            return $parent->fresh('students');
        });
    }

    public function reject(ParentAccountRequest $request, string $reason, User $admin): ParentAccountRequest
    {
        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // TODO: notify the requesting student — see note in approve().

        return $request->fresh();
    }
}
