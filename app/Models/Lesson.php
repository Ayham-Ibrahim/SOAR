<?php

namespace App\Models;

use App\Models\Concerns\Orderable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lesson belongs to BOTH a course and a unit, and the two must agree on
 * subject: lesson.course.subject_id MUST equal lesson.unit.subject_id. This
 * is not a DB constraint (it spans two separate parent chains) — it's
 * enforced in StoreLessonRequest/UpdateLessonRequest via an after-validation
 * hook. Do not bypass that check when creating/updating lessons elsewhere.
 */
class Lesson extends Model
{
    use HasFactory, Orderable;

    protected $fillable = [
        'course_id',
        'unit_id',
        'title',
        'order',
        'is_free',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }
}
