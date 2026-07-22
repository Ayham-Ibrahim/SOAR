<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The submitted password is hashed at creation and, on approval, copied
 * as-is into the new `parents` row — the admin never sees or resets it.
 * Never expose `password` in a response; it's hidden below as a hard
 * guarantee even though this codebase returns raw models directly.
 */
class ParentAccountRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by_student_id',
        'parent_name',
        'parent_phone',
        'password',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'created_parent_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_student_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdParent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'created_parent_id');
    }
}
